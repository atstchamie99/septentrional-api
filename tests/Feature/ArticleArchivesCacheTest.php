<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ArticleArchivesCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_archives_remain_readable_after_database_cache_round_trip(): void
    {
        config(['cache.default' => 'database']);
        $this->travelTo(now()->setDate(2026, 10, 21)->startOfDay());

        // An older deployment may have cached an HTTP response under the old key.
        Cache::put('v1:articles:archives-mois', response()->json(['stale' => true]), 300);

        $first = $this->getJson('/api/v2/articles/archives-mois');
        $first->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.periode', '2026-05')
            ->assertJsonPath('data.5.periode', '2026-10')
            ->assertJsonPath('data.5.total_articles', 0);

        $this->assertIsArray(Cache::get('v2:articles:archives-mois'));

        $this->getJson('/api/v2/articles/archives-mois')
            ->assertOk()
            ->assertExactJson($first->json());
    }
}
