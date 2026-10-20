<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SanitizeLogs
{
    /**
     * Liste des champs sensibles à masquer dans les logs.
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'token',
        'api_token',
        'access_token',
        'refresh_token',
        'secret',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'email',
        'phone',
        'address',
        'google2fa_secret',
        'otp_secret',
        'code',
        'challenge_token',
        'auth_token',
        'otp_reauth',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Masquer les paramètres sensibles dans le contexte de log de la requête
        $this->maskRequestData($request);

        return $response;
    }

    /**
     * Masque les données sensibles dans la requête avant qu'elles ne soient loguées.
     */
    protected function maskRequestData(Request $request): void
    {
        $masked = '***MASKED***';

        // Masquer dans les champs du formulaire
        foreach ($this->sensitiveFields as $field) {
            if ($request->has($field)) {
                $request->merge([$field => $masked]);
            }
        }

        // Masquer dans l'en-tête Authorization
        if ($request->hasHeader('Authorization')) {
            $request->headers->set('Authorization', 'Bearer ***MASKED***');
        }

        // Masquer les cookies d'authentification
        if ($request->hasHeader('Cookie')) {
            $cookies = $request->cookies->all();
            foreach (['auth_token', 'otp_reauth', 'XSRF-TOKEN'] as $cookie) {
                if (isset($cookies[$cookie])) {
                    $request->cookies->set($cookie, $masked);
                }
            }
        }
    }

    public function maskSensitiveData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $this->sensitiveFields, true)) {
                $data[$key] = '***MASKED***';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }

        return $data;
    }
}
