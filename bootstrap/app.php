<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Cette API est 100% stateless (Bearer tokens transportés via un cookie
        // HttpOnly "auth_token", extrait par le middleware AuthCookieToHeader).
        // On n'active PAS statefulApi() pour éviter le pipeline session + CSRF
        // Ne jamais faire confiance à tous les proxys : l'adresse IP alimente les
        // limites de débit et l'anti-doublon des écoutes. Les IP/CIDR des proxys
        // réellement administrés doivent être déclarés explicitement.
        // Ne jamais tenter de rediriger
        // vers une route "login" web (qui n'existe pas ici) quand l'auth échoue, sous
        // peine de RouteNotFoundException non catchée (500 au lieu d'un 401 propre).
        Authenticate::redirectUsing(fn () => null);

        // Middleware personnalisé pour limiter les tentatives de login
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'api-key' => \App\Http\Middleware\EnsureApiKey::class,
            'auth-cookie' => \App\Http\Middleware\AuthCookieToHeader::class,
            'trusted-cookie-origin' => \App\Http\Middleware\EnsureTrustedCookieOrigin::class,
            'regenerate-session' => \App\Http\Middleware\RegenerateSession::class,
            'sanitize-logs' => \App\Http\Middleware\SanitizeLogs::class,
            'secure-headers' => \App\Http\Middleware\SecureHeaders::class,
            'throttle-api-login' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api-login',
            'throttle-api-otp' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api-otp',
            'throttle-api-comments' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api-comments',
            'throttle-api-listens' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api-listens',
            'throttle-api-newsletter' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api-newsletter',
            'throttle-api-newsletter-send' => \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api-newsletter-send',
        ]);

        // Appliquer le throttle par défaut pour toutes les routes API (utilise le limiteur nommé "api")
        // Ne pas forcer l'utilisation de Redis ici (évite l'erreur si l'extension PHP redis est absente)
        $middleware->throttleApi('api');

        // Appliquer les middlewares de sécurité pour toutes les routes API et web
        // HandleCors doit être exécuté EN PREMIER pour ajouter les headers CORS
        // AuthCookieToHeader doit être exécuté AVANT VerifyCsrfToken
        $middleware->prepend(\Illuminate\Http\Middleware\HandleCors::class);

        $middleware->prependToGroup('api', [
            \App\Http\Middleware\AuthCookieToHeader::class, // ✅ Extrait le cookie auth_token → Bearer header
            \App\Http\Middleware\EnsureTrustedCookieOrigin::class,
        ]);
        
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\SanitizeLogs::class,
            \App\Http\Middleware\SecureHeaders::class,
        ]);

        $middleware->appendToGroup('web', [
            \App\Http\Middleware\SecureHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Les données fournies sont invalides.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Ressource introuvable.',
                ], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Non authentifié.',
                ], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Accès interdit.',
                ], 403);
            }
        });
    })->create();
