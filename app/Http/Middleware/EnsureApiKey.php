<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige une clé API valide (header X-API-Key) sur toutes les routes /api/v2/*.
 *
 * Cette clé n'est PAS un mécanisme d'authentification utilisateur (voir Sanctum
 * pour ça) : sur les routes publiques, elle est embarquée dans le bundle du SPA
 * et donc visible de n'importe quel visiteur. Son rôle est de filtrer les
 * clients qui n'ont pas intégré l'API (bots/scrapers basiques), pas de protéger
 * un secret. Sur les routes admin, elle s'ajoute en couche supplémentaire
 * au-dessus du token Sanctum utilisateur.
 */
class EnsureApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        // Header uniquement : un paramètre de requête finirait dans les logs
        // d'accès serveur, l'historique du navigateur et l'en-tête Referer.
        // Aucun appelant légitime n'en a besoin — les seules routes atteintes
        // sans pouvoir poser de header custom (sitemap, stream, téléchargements,
        // PDF communiqués) sont explicitement exclues de ce middleware.
        $provided = $request->header('X-API-Key');

        if (! is_string($provided) || $provided === '') {
            return response()->json(['message' => 'Clé API manquante.'], 401);
        }

        $apiKey = ApiKey::findValid($provided);

        if (! $apiKey) {
            return response()->json(['message' => 'Clé API invalide ou révoquée.'], 401);
        }

        // Mise à jour "last_used_at" limitée à une fois par minute par clé pour
        // éviter une écriture DB sur chaque requête.
        if (! $apiKey->last_used_at || $apiKey->last_used_at->lt(now()->subMinute())) {
            $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
