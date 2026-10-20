<?php
// app/Http/Controllers/Api/AuthController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Services\EmailOtpService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const OTP_CHALLENGE_TTL_MINUTES = 5;
    private const OTP_REAUTH_CACHE_PREFIX = '2fa:reauth:user:';

    /**
     * Connexion (étape 1 : email + mot de passe).
     * Pour les comptes admin/rédacteur, ne délivre pas encore de jeton :
     * renvoie un challenge OTP à valider via verifyOtp().
     */
    public function login(Request $request, TwoFactorService $twoFactor)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
            'device'   => 'nullable|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Log::channel('security')->warning('Échec de connexion (identifiants invalides)', [
                'email' => $request->email,
                'ip'    => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        if (!$user->is_active) {
            Log::channel('security')->warning('Tentative de connexion sur compte désactivé', [
                'user_id' => $user->id,
                'ip'      => $request->ip(),
            ]);

            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        if (!$user->necessite2FA()) {
            Log::channel('security')->info('Connexion réussie', ['user_id' => $user->id, 'ip' => $request->ip()]);

            return $this->issueToken($user, $request->device, true);
        }

        $challengeToken = Str::random(40);

        if (!$user->google2fa_enabled) {
            // Première connexion staff : l'OTP n'est pas encore configurée, on force sa mise en place.
            $secret = $twoFactor->generateSecretKey();

            Cache::put("2fa:setup:{$challengeToken}", [
                'user_id' => $user->id,
                'secret'  => $secret,
            ], now()->addMinutes(self::OTP_CHALLENGE_TTL_MINUTES));

            return response()->json([
                'requires_otp'      => true,
                'otp_setup_required' => true,
                'challenge_token'   => $challengeToken,
                'otp_secret'        => $secret,
                'otp_qr_svg'        => $twoFactor->getQrCodeSvg(config('app.name'), $user->email, $secret),
            ]);
        }

        Cache::put("2fa:login:{$challengeToken}", $user->id, now()->addMinutes(self::OTP_CHALLENGE_TTL_MINUTES));

        return response()->json([
            'requires_otp'       => true,
            'otp_setup_required' => false,
            'challenge_token'    => $challengeToken,
        ]);
    }

    /**
     * Connexion (étape 2 : code Google Authenticator).
     * Valide soit la mise en place initiale de l'OTP, soit une connexion déjà configurée.
     */
    public function verifyOtp(Request $request, TwoFactorService $twoFactor, EmailOtpService $emailOtp)
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'code'            => 'required|string',
            'device'          => 'nullable|string|max:255',
        ]);

        if ($emailOtp->find($request->challenge_token)) {
            $record = $emailOtp->verify($request->challenge_token, $request->code, 'login');
            $user = User::find($record['user_id']);
            if (! $user || ! $user->is_active || ! $user->necessite2FA() || $user->email !== $record['email']) {
                return response()->json(['message' => 'Connexion indisponible.'], 403);
            }
            Log::channel('security')->info('Connexion réussie (OTP e-mail)', ['user_id' => $user->id]);

            return $this->issueToken($user, $request->device, true);
        }

        // Le challenge reste valable jusqu'à son expiration tant que le code est
        // invalide. Cache::pull() le supprimait dès le premier essai : après une
        // erreur ponctuelle (ou un 500), tous les essais suivants échouaient 422.
        $setup = Cache::get("2fa:setup:{$request->challenge_token}");

        if ($setup) {
            if (!$twoFactor->verifyKey($setup['secret'], $request->code)) {
                Log::channel('security')->warning('Code OTP invalide (mise en place)', ['user_id' => $setup['user_id'], 'ip' => $request->ip()]);

                return response()->json(['message' => 'Code invalide.'], 422);
            }

            $user = User::findOrFail($setup['user_id']);
            $user->update(['google2fa_secret' => $setup['secret'], 'google2fa_enabled' => true]);

            Log::channel('security')->info('2FA activée', ['user_id' => $user->id, 'ip' => $request->ip()]);

            $response = $this->issueToken($user, $request->device, true);
            Cache::forget("2fa:setup:{$request->challenge_token}");

            return $response;
        }

        $userId = Cache::get("2fa:login:{$request->challenge_token}");

        if (!$userId) {
            return response()->json([
                'message' => 'Session de connexion expirée, veuillez vous reconnecter.',
                'code' => 'OTP_CHALLENGE_EXPIRED',
            ], 422);
        }

        $user = User::findOrFail($userId);

        $secret = $this->otpSecret($user);

        if ($secret === null) {
            Log::channel('security')->warning('Configuration OTP illisible', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'La configuration OTP est invalide. Contactez un administrateur.'], 422);
        }

        if (!$twoFactor->verifyKey($secret, $request->code)) {
            Log::channel('security')->warning('Code OTP invalide', ['user_id' => $user->id, 'ip' => $request->ip()]);

            return response()->json(['message' => 'Code invalide.'], 422);
        }

        Log::channel('security')->info('Connexion réussie (OTP)', ['user_id' => $user->id, 'ip' => $request->ip()]);

        $response = $this->issueToken($user, $request->device, true);
        Cache::forget("2fa:login:{$request->challenge_token}");

        return $response;
    }

    /**
     * Réactive une session expirée avec le seul code OTP, si le navigateur
     * possède encore son ticket HttpOnly de ré-authentification.
     */
    public function reverifyOtp(Request $request, TwoFactorService $twoFactor, EmailOtpService $emailOtp)
    {
        $request->validate([
            'code' => 'required|string',
            'challenge_token' => 'nullable|string|max:40',
            'device' => 'nullable|string|max:255',
        ]);

        $ticket = (string) $request->cookie('otp_reauth', '');
        [$userId, $secret] = array_pad(explode('.', $ticket, 2), 2, null);

        if (! ctype_digit((string) $userId) || ! is_string($secret) || strlen($secret) < 40) {
            return response()->json(['message' => 'La ré-authentification a expiré. Veuillez vous reconnecter.'], 401);
        }

        $cacheKey = self::OTP_REAUTH_CACHE_PREFIX . $userId;
        $ticketHash = Cache::get($cacheKey);

        if (! is_string($ticketHash) || ! hash_equals($ticketHash, hash('sha256', $ticket))) {
            return response()->json(['message' => 'La ré-authentification a expiré. Veuillez vous reconnecter.'], 401);
        }

        $user = User::find($userId);
        if (! $user || ! $user->is_active || ! $user->necessite2FA()) {
            Cache::forget($cacheKey);

            return response()->json(['message' => 'La ré-authentification est indisponible. Veuillez vous reconnecter.'], 401);
        }

        if ($request->filled('challenge_token')) {
            $record = $emailOtp->verify($request->challenge_token, $request->code, 'reauth', hash('sha256', $ticket));
            if ($record['user_id'] !== $user->id || $record['email'] !== $user->email) {
                return response()->json(['message' => 'Ré-authentification indisponible.'], 401);
            }
            Cache::forget($cacheKey);

            return $this->issueToken($user, $request->device, true);
        }

        if (! $user->google2fa_enabled) {
            return response()->json(['message' => 'Utilisez un code envoyé par e-mail.'], 422);
        }

        $secret = $this->otpSecret($user);

        if ($secret === null) {
            return response()->json([
                'message' => 'La configuration Authenticator est invalide. Utilisez un code envoyé par e-mail.',
            ], 422);
        }

        if (! $twoFactor->verifyKey($secret, $request->code)) {
            Log::channel('security')->warning('Code OTP de ré-authentification invalide', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Code invalide.'], 422);
        }

        // Rotation du ticket : un code OTP valide ne prolonge jamais le même secret.
        Cache::forget($cacheKey);
        Log::channel('security')->info('Session réactivée par OTP', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return $this->issueToken($user, $request->device, true);
    }

    public function sendEmailOtp(Request $request, EmailOtpService $emailOtp)
    {
        $request->validate(['challenge_token' => 'required|string|max:40']);
        $token = $request->challenge_token;
        $setup = Cache::get("2fa:setup:{$token}");
        $record = $emailOtp->find($token);
        $userId = $setup['user_id'] ?? Cache::get("2fa:login:{$token}");
        if (! $userId && $record && $record['purpose'] === 'login') {
            $userId = $record['user_id'];
        }
        $user = $userId ? User::find($userId) : null;
        if (! $user || ! $user->is_active || ! $user->necessite2FA()) {
            return response()->json([
                'message' => 'Session de connexion expirée, veuillez vous reconnecter.',
                'code' => 'OTP_CHALLENGE_EXPIRED',
            ], 422);
        }

        $challenge = $emailOtp->issue($user);
        if ($record) {
            Cache::forget("2fa:email:{$token}");
        }

        return response()->json($challenge);
    }

    public function sendReauthEmailOtp(Request $request, EmailOtpService $emailOtp)
    {
        $ticket = (string) $request->cookie('otp_reauth', '');
        [$userId, $secret] = array_pad(explode('.', $ticket, 2), 2, null);
        $ticketHash = ctype_digit((string) $userId)
            ? Cache::get(self::OTP_REAUTH_CACHE_PREFIX . $userId) : null;
        if (! is_string($secret) || strlen($secret) < 40 || ! is_string($ticketHash)
            || ! hash_equals($ticketHash, hash('sha256', $ticket))) {
            return response()->json(['message' => 'La ré-authentification a expiré. Veuillez vous reconnecter.'], 401);
        }
        $user = User::find($userId);
        if (! $user || ! $user->is_active || ! $user->necessite2FA()) {
            return response()->json(['message' => 'Ré-authentification indisponible.'], 401);
        }

        return response()->json($emailOtp->issue($user, 'reauth', hash('sha256', $ticket)));
    }

    private function issueToken(User $user, ?string $device, bool $issueOtpReauthTicket = false)
    {
        // Les valeurs .env sont des chaînes ; Carbon exige un entier pour
        // addMinutes(). Le cast évite un 500 au moment de créer le jeton.
        $expiration = (int) config('sanctum.expiration', 60);

        $token = $user->createToken(
            $device ?? 'api-token',
            ['*'],
            $expiration ? now()->addMinutes($expiration) : null
        )->plainTextToken;

        ['secure' => $secure, 'sameSite' => $sameSite] = $this->cookieAttributes();

        $response = response()->json([
            'user'  => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'role'   => $user->role,
                'avatar' => $user->avatar ? asset('storage/' . $user->avatar) : null,
            ],
        ])->cookie(
            'auth_token',
            $token,
            $expiration ? $expiration * 60 : null,
            '/',
            $this->cookieDomain(),
            $secure,
            true,
            false,
            $sameSite
        );

        if (! $issueOtpReauthTicket || ! $user->necessite2FA()) {
            return $response;
        }

        $reauthExpiration = (int) config('sanctum.otp_reauth_expiration');
        $reauthTicket = $user->id . '.' . Str::random(64);
        Cache::put(
            self::OTP_REAUTH_CACHE_PREFIX . $user->id,
            hash('sha256', $reauthTicket),
            now()->addMinutes($reauthExpiration),
        );

        return $response->cookie(
            'otp_reauth',
            $reauthTicket,
            $reauthExpiration * 60,
            '/',
            $this->cookieDomain(),
            $secure,
            true,
            false,
            $sameSite,
        );
    }

    /**
     * Lit un secret OTP chiffré et migre, lors de son premier usage, les
     * anciens secrets Base32 qui auraient été stockés en clair avant
     * l'introduction du cast "encrypted". Une valeur illisible ne doit jamais
     * transformer une tentative de connexion en erreur 500.
     */
    private function otpSecret(User $user): ?string
    {
        try {
            return $user->google2fa_secret;
        } catch (DecryptException) {
            $legacySecret = (string) $user->getRawOriginal('google2fa_secret');

            if (! preg_match('/\\A[A-Z2-7]{16,}={0,6}\\z/i', $legacySecret)) {
                return null;
            }

            $secret = strtoupper(rtrim($legacySecret, '='));
            $user->forceFill(['google2fa_secret' => $secret])->save();

            Log::channel('security')->notice('Secret OTP historique chiffré à la demande', [
                'user_id' => $user->id,
            ]);

            return $secret;
        }
    }

    /**
     * Domaine du cookie auth_token (null en local pour localhost).
     */
    private function cookieDomain(): ?string
    {
        $domain = config('session.domain');

        return $domain !== null && $domain !== '' ? $domain : null;
    }

    /**
     * Détermine les attributs secure et sameSite du cookie auth_token
     * en fonction du contexte (dev/prod, même origine ou cross-origin).
     */
    private function cookieAttributes(): array
    {
        $isProduction = app()->environment('production');
        $frontendUrl = env('FRONTEND_URL');
        $frontendHost = $frontendUrl ? parse_url($frontendUrl, PHP_URL_HOST) : null;
        $frontendPort = $frontendUrl ? parse_url($frontendUrl, PHP_URL_PORT) : null;
        $requestHost = request()->getHost();
        $requestPort = request()->getPort();

        // En dev local (proxy Vite ou ports différents), SameSite=None sans Secure
        // est rejeté par les navigateurs — utiliser Lax.
        $localHosts = ['localhost', '127.0.0.1'];
        $isLocalDev = ! $isProduction
            && in_array($frontendHost, $localHosts, true)
            && in_array($requestHost, $localHosts, true);

        if ($isLocalDev) {
            return ['secure' => false, 'sameSite' => 'Lax'];
        }

        $isCrossOrigin = $frontendHost !== $requestHost
            || ($frontendHost === $requestHost && $frontendPort && $frontendPort !== $requestPort);

        if ($isCrossOrigin) {
            return ['secure' => true, 'sameSite' => 'None'];
        }

        return ['secure' => $isProduction, 'sameSite' => 'Lax'];
    }

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        Log::channel('security')->info('Déconnexion', ['user_id' => $request->user()->id, 'ip' => $request->ip()]);

        $request->user()->currentAccessToken()->delete();
        Cache::forget(self::OTP_REAUTH_CACHE_PREFIX . $request->user()->id);

        ['secure' => $secure, 'sameSite' => $sameSite] = $this->cookieAttributes();

        return response()->json(['message' => 'Déconnecté avec succès.'])
            ->cookie(
                'auth_token',
                '',
                -1, // Expiration immédiate
                '/',
                $this->cookieDomain(),
                $secure,
                true, // httpOnly
                false, // raw
                $sameSite
            )
            ->cookie('otp_reauth', '', -1, '/', $this->cookieDomain(), $secure, true, false, $sameSite);
    }

    /**
     * Déconnexion de tous les appareils
     */
    public function logoutAll(Request $request)
    {
        Log::channel('security')->info('Déconnexion de tous les appareils', ['user_id' => $request->user()->id, 'ip' => $request->ip()]);

        $request->user()->tokens()->delete();
        Cache::forget(self::OTP_REAUTH_CACHE_PREFIX . $request->user()->id);

        ['secure' => $secure, 'sameSite' => $sameSite] = $this->cookieAttributes();

        return response()->json(['message' => 'Déconnecté de tous les appareils.'])
            ->cookie(
                'auth_token',
                '',
                -1, // Expiration immédiate
                '/',
                $this->cookieDomain(),
                $secure,
                true, // httpOnly
                false, // raw
                $sameSite
            )
            ->cookie('otp_reauth', '', -1, '/', $this->cookieDomain(), $secure, true, false, $sameSite);
    }

    /**
     * Changement de mot de passe (utilisateur déjà connecté).
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'          => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($request->password)]);

        $user->tokens()->delete();
        Cache::forget(self::OTP_REAUTH_CACHE_PREFIX . $user->id);

        ['secure' => $secure, 'sameSite' => $sameSite] = $this->cookieAttributes();

        Log::channel('security')->info('Mot de passe changé', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json(['message' => 'Mot de passe mis à jour. Veuillez vous reconnecter.'])
            ->cookie(
                'auth_token',
                '',
                -1,
                '/',
                $this->cookieDomain(),
                $secure,
                true,
                false,
                $sameSite
            )
            ->cookie('otp_reauth', '', -1, '/', $this->cookieDomain(), $secure, true, false, $sameSite);
    }

    /**
     * Demande de réinitialisation de mot de passe (mot de passe oublié).
     * Renvoie toujours le même message, que l'email existe ou non, pour éviter l'énumération de comptes.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        Log::channel('security')->info('Demande de réinitialisation de mot de passe', ['email' => $request->email, 'ip' => $request->ip()]);

        return response()->json([
            'message' => "Si un compte existe avec cet email, un lien de réinitialisation vient d'être envoyé.",
        ]);
    }

    /**
     * Réinitialisation du mot de passe via le jeton reçu par email.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request) {
                $user->update(['password' => Hash::make($password)]);
                $user->tokens()->delete();
                Cache::forget(self::OTP_REAUTH_CACHE_PREFIX . $user->id);

                event(new PasswordReset($user));

                Log::channel('security')->info('Mot de passe réinitialisé', ['user_id' => $user->id, 'ip' => $request->ip()]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Ce lien de réinitialisation est invalide ou a expiré.'], 422);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé avec succès.']);
    }

    /**
     * Profil connecté
     */
    public function me(Request $request)
    {
        $user = $request->user()->loadCount(['articles', 'videos']);

        return response()->json([
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'role'            => $user->role,
            'avatar'          => $user->avatar ? asset('storage/' . $user->avatar) : null,
            'is_active'       => $user->is_active,
            'stats'           => [
                'articles'   => $user->articles_count,
                'videos'     => $user->videos_count,
            ],
            'created_at'      => $user->created_at,
        ]);
    }
}
