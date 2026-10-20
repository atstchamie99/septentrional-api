<?php

namespace Database\Seeders;

use App\Models\ApiKey;
use Illuminate\Database\Seeder;

class ApiKeySeeder extends Seeder
{
    /**
     * Provisionne la clé API de développement local utilisée par
     * septentrional-frontend/.env.development (VITE_API_KEY), pour que l'API
     * locale fonctionne dès le clone du dépôt sans étape manuelle dans le
     * CMS. Cette clé n'est pas un secret (voir EnsureApiKey) : sa valeur est
     * volontairement fixe et connue de tous les développeurs.
     *
     * Ne PAS lancer en production — utiliser le CMS (Administration > Clés
     * API) pour générer une vraie clé de production, aléatoire et révocable.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('ApiKeySeeder ne doit pas être exécuté en production. Génère une clé depuis le CMS.');

            return;
        }

        $plainText = 'sept_localdev.not-a-secret-local-development-only';
        $prefix = 'sept_localdev';

        ApiKey::updateOrCreate(
            ['prefix' => $prefix],
            [
                'name' => 'Développement local',
                'key_hash' => hash('sha256', $plainText),
                'created_by' => null,
                'is_active' => true,
            ],
        );
    }
}
