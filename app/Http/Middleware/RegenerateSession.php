<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegenerateSession
{
    /**
     * Régénère la session après l'authentification pour éviter les attaques de fixation de session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/login') && $request->method() === 'POST') {
            $response = $next($request);
            
            // Régénère la session uniquement si l'utilisateur est authentifié
            if ($request->user()) {
                $request->session()->regenerate();
                $request->session()->regenerateToken();
            }
            
            return $response;
        }
        
        return $next($request);
    }
}
