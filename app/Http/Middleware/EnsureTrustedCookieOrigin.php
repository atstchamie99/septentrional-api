<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cookie-authenticated API mutations need an origin check because API routes
 * intentionally do not use Laravel's session-based CSRF middleware.
 */
class EnsureTrustedCookieOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasCookie('auth_token') && ! $request->hasCookie('otp_reauth')) {
            return $next($request);
        }

        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $origin = rtrim((string) $request->headers->get('Origin'), '/');
        $allowedOrigins = array_filter(array_merge(
            (array) config('cors.allowed_origins', []),
            [config('app.url'), env('FRONTEND_URL')],
        ));
        $allowedOrigins[] = $request->getSchemeAndHttpHost();
        $allowedOrigins = array_map(static fn (string $value) => rtrim($value, '/'), $allowedOrigins);

        if ($origin === '' || ! in_array($origin, $allowedOrigins, true)) {
            return response()->json(['message' => 'Origine de requête non autorisée.'], 403);
        }

        return $next($request);
    }
}
