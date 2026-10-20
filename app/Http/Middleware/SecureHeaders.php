<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeaders
{
    /**
     * Applique les en-têtes de sécurité HTTP aux réponses.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $preservesPublicCache = $request->routeIs('v2.seo.*');

        // 1. X-Frame-Options : Empêche l'affichage dans un iframe (clickjacking)
        $response->headers->set('X-Frame-Options', 'DENY');

        // 2. X-Content-Type-Options : Empêche le MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // 3. X-XSS-Protection : Active la protection XSS du navigateur
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // 4. Referrer-Policy : Contrôle les informations envoyées dans le header Referer
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 5. Permissions-Policy : Restreint les fonctionnalités du navigateur (ex: géolocalisation, caméra)
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=()'
        );

        // 6. Content-Security-Policy (CSP) : Restreint les sources autorisées
        // À adapter selon les besoins de ton application
        $cspDirectives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'", 'https:', 'http:', 'http://localhost:8000'],
            'style-src' => ["'self'", "'unsafe-inline'", 'https:', 'http:', 'cdnjs.cloudflare.com', 'http://localhost:8000'],
            'img-src' => ["'self'", 'data:', 'https:', 'http:', 'cdnjs.cloudflare.com', 'http://localhost:8000'],
            'font-src' => ["'self'", 'https:', 'http:', 'cdnjs.cloudflare.com', 'http://localhost:8000'],
            'connect-src' => ["'self'", 'https:', 'http:', 'http://localhost:8000'],
            'frame-ancestors' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'", 'http:', 'https:', 'http://localhost:8000'],
        ];

        $cspHeader = $this->buildCspHeader($cspDirectives);
        $response->headers->set('Content-Security-Policy', $cspHeader);

        // 7. Strict-Transport-Security (HSTS) : Force HTTPS (à activer en production)
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // 8. Cache-Control : Désactive la mise en cache pour les pages sensibles
        if ($request->is('api/*') && ! $preservesPublicCache) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }

    /**
     * Retourne uniquement l'origine sûre de FRONTEND_URL pour éviter toute
     * injection de directive CSP via une valeur d'environnement mal formée.
     */
    private function frontendOrigin(): string
    {
        $frontendUrl = (string) config('app.frontend_url');
        $parts = parse_url($frontendUrl);
        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! $host) {
            return "'none'";
        }

        $origin = $scheme.'://'.$host;
        if (isset($parts['port'])) {
            $origin .= ':'.(int) $parts['port'];
        }

        return $origin;
    }

    /**
     * Construit l'en-tête Content-Security-Policy à partir des directives.
     */
    protected function buildCspHeader(array $directives): string
    {
        $parts = [];
        foreach ($directives as $directive => $sources) {
            $parts[] = $directive.' '.implode(' ', $sources);
        }

        return implode('; ', $parts);
    }
}
