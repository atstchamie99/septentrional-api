<?php

namespace Tests;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Toutes les routes /api/v2/* exigent désormais une clé API (middleware
        // EnsureApiKey). On en génère une valide pour chaque test et on l'attache
        // par défaut à toutes les requêtes du client de test, pour que les tests
        // Feature existants continuent d'exercer le comportement réel des
        // contrôleurs plutôt que d'échouer avant même de les atteindre.
        if (Schema::hasTable('api_keys')) {
            $plainKey = ApiKey::generate('test-suite', null)['plainText'];
            $this->withHeader('X-API-Key', $plainKey);
        }
    }
}
