<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active) {
            return response()->json(['message' => 'Non authentifié ou compte inactif.'], 401);
        }

        if (!in_array($user->role, $roles)) {
            Log::channel('security')->warning('Accès refusé (rôle insuffisant)', [
                'user_id' => $user->id,
                'role'    => $user->role,
                'requis'  => $roles,
                'route'   => $request->path(),
                'ip'      => $request->ip(),
            ]);

            return response()->json(['message' => 'Accès interdit. Rôle insuffisant.'], 403);
        }

        // Régénère la session pour éviter les attaques de fixation de session
        // (uniquement si la session est disponible, pas pour les requêtes API)
        if ($request->hasSession() && !$request->session()->has('regenerated_after_auth')) {
            $request->session()->regenerate();
            $request->session()->put('regenerated_after_auth', true);
        }

        return $next($request);
    }
}
