<?php

namespace App\Services;

use App\Mail\EmailOtpMail;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class EmailOtpService
{
    public function issue(User $user, string $purpose = 'login', ?string $ticketHash = null): array
    {
        // Une limite par compte protège aussi contre plusieurs challenges simultanés.
        if (! Cache::add("2fa:email:cooldown:{$user->id}", true, 60)) {
            throw new HttpResponseException(response()->json([
                'message' => 'Attendez une minute avant de demander un nouveau code.',
            ], 429, ['Retry-After' => '60']));
        }

        $token = Str::random(40);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);
        $record = [
            'user_id' => $user->id,
            'email' => $user->email,
            'purpose' => $purpose,
            'ticket_hash' => $ticketHash,
            'code_hash' => $this->digest($token, $code),
            'attempts' => 0,
            'expires_at' => $expiresAt->timestamp,
        ];
        Cache::put("2fa:email:{$token}", $record, $expiresAt);

        try {
            // Envoi immédiat : les dix minutes ne dépendent pas d'un worker de queue.
            if (app()->isProduction() && in_array(config('mail.default'), ['log', 'array', 'failover'], true)) {
                throw new \RuntimeException('OTP mail transport must deliver without logging codes.');
            }
            Mail::to($user->email)->send(new EmailOtpMail($code));
        } catch (Throwable $e) {
            Cache::forget("2fa:email:{$token}");
            Cache::forget("2fa:email:cooldown:{$user->id}");
            Log::channel('security')->warning('Envoi OTP e-mail impossible', ['user_id' => $user->id]);
            throw new HttpResponseException(response()->json([
                'message' => "L'envoi du code est indisponible. Réessayez ou utilisez Authenticator.",
            ], 503));
        }

        return [
            'requires_otp' => true,
            'otp_setup_required' => false,
            'otp_method' => 'email',
            'challenge_token' => $token,
            'expires_in' => 600,
        ];
    }

    public function find(string $token): ?array
    {
        return Cache::get("2fa:email:{$token}");
    }

    public function verify(string $token, string $code, string $purpose, ?string $ticketHash = null): array
    {
        return Cache::lock("2fa:email:lock:{$token}", 10)->block(3, function () use ($token, $code, $purpose, $ticketHash) {
            $record = $this->find($token);
            if (! $record || $record['expires_at'] <= now()->timestamp) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Session de connexion expirée, veuillez vous reconnecter.',
                    'code' => 'OTP_CHALLENGE_EXPIRED',
                ], 422));
            }
            if ($record['purpose'] !== $purpose || $record['ticket_hash'] !== $ticketHash) {
                throw new HttpResponseException(response()->json(['message' => 'Code invalide.'], 422));
            }
            if (! hash_equals($record['code_hash'], $this->digest($token, $code))) {
                $record['attempts']++;
                if ($record['attempts'] >= 5) {
                    Cache::forget("2fa:email:{$token}");
                    throw new HttpResponseException(response()->json([
                        'message' => 'Trop de codes incorrects. Veuillez recommencer la connexion.',
                        'code' => 'OTP_ATTEMPTS_EXHAUSTED',
                    ], 422));
                }
                Cache::put("2fa:email:{$token}", $record, now()->setTimestamp($record['expires_at']));
                throw new HttpResponseException(response()->json(['message' => 'Code invalide.'], 422));
            }
            Cache::forget("2fa:email:{$token}");

            return $record;
        });
    }

    private function digest(string $token, string $code): string
    {
        return hash_hmac('sha256', $token.'|'.$code, (string) config('app.key'));
    }
}
