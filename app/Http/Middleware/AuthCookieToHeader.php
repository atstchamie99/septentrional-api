<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Extrait le cookie HttpOnly "auth_token" et l'injecte en tant que
 * header Authorization: Bearer pour que Sanctum puisse l'authentifier.
 *
 * Cela permet au frontend (SPA) d'utiliser exclusivement des cookies HttpOnly
 * sans jamais manipuler le token en JavaScript.
 */
class AuthCookieToHeader
{
    public function handle(Request $request, Closure $next)
    {
        // Ne rien faire si un header Authorization est déjà présent
        if ($request->headers->has('Authorization')) {
            return $next($request);
        }

        $cookieHeader = $request->headers->get('cookie');

        // Essayer d'abord la méthode standard
        $token = $request->cookie('auth_token');

        // Si non trouvé, parser manuellement le header Cookie
        if (empty($token) && $cookieHeader) {
            $cookies = [];
            $cookieParts = explode(';', $cookieHeader);
            foreach ($cookieParts as $part) {
                $part = trim($part);
                if (empty($part)) continue;
                $cookieParts2 = explode('=', $part, 2);
                if (count($cookieParts2) === 2) {
                    $name = trim($cookieParts2[0]);
                    $value = trim($cookieParts2[1]);
                    $cookies[$name] = $value;
                }
            }
            $token = $cookies['auth_token'] ?? null;
        }

        if ($token) {
            // Le cookie peut contenir des caractères encodés (ex. "%7C" pour "|").
            // Décoder pour fournir le bon format attendu par Sanctum (id|token).
            $token = urldecode($token);
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}
