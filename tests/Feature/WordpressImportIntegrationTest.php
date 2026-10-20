<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Categorie;
use App\Models\FlashInfo;
use App\Models\Page;
use App\Models\User;
use App\Models\WordpressImport;
use App\Services\Wordpress\WordpressImporter;
use App\Support\PublicCache;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Tests explicites contre une copie locale, jamais exécutés en CI par défaut. */
class WordpressImportIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        if (getenv('SEPTENTRIONAL_WORDPRESS_INTEGRATION') !== '1') {
            $this->markTestSkipped('Activer avec php scripts/test-wordpress-import.php après l’import local.');
        }
        if (getenv('DB_DATABASE') !== 'SeptentrionalTest') {
            throw new \RuntimeException('Les tests d’import sont réservés à SeptentrionalTest.');
        }
        parent::setUp();
    }

    public function test_imported_counts_match_the_editorial_source(): void
    {
        $importer = app(WordpressImporter::class);
        $importer->configure('wordpress', config('wordpress.prefix'));
        $inventory = $importer->inventory();
        $this->assertSame($inventory['articles'], Article::count());
        $this->assertSame($inventory['categories'], WordpressImport::where('entity', 'category')->count());
        $this->assertSame($inventory['images'], WordpressImport::where('entity', 'image')->count());
        $this->assertSame($inventory['livres_pdf'], WordpressImport::where('entity', 'book')->count());
        $this->assertSame($inventory['galeries_video'], WordpressImport::where('entity', 'gallery')->count());
        $source = DB::connection('wordpress')->table(config('wordpress.prefix').'posts');
        $this->assertSame((clone $source)->where('post_type', 'post')->where('post_status', 'publish')->count(), Article::publies()->count());
        $this->assertSame((clone $source)->where('post_type', 'post')->where('post_status', 'draft')->count(), Article::where('statut', 'brouillon')->count());
    }

    public function test_an_article_is_findable_in_a_secondary_category(): void
    {
        $article = Article::publies()->whereHas('categories', fn ($q) => $q->whereColumn('categories.id', '!=', 'articles.categorie_id'))->firstOrFail();
        $category = $article->categories()->where('categories.id', '!=', $article->categorie_id)->firstOrFail();
        $this->getJson('/api/v2/articles?'.http_build_query(['categorie' => $category->slug, 'q' => $article->titre]))
            ->assertOk()->assertJsonFragment(['id' => $article->id, 'titre' => $article->titre]);
        $this->getJson('/api/v2/articles/'.$article->slug)
            ->assertOk()->assertJsonFragment(['id' => $category->id, 'slug' => $category->slug]);
        $this->getJson('/api/v2/articles?categorie[]=invalid')->assertUnprocessable();
        $this->getJson('/api/v2/articles?categorie=')->assertOk();
    }

    public function test_legacy_urls_resolve_only_public_content(): void
    {
        $published = Article::publies()->firstOrFail();
        $mapping = WordpressImport::where('entity', 'article')->where('target_id', $published->id)->firstOrFail();
        $this->getJson('/api/v2/legacy/resolve?'.http_build_query(['path' => $mapping->original_path]))
            ->assertOk()->assertJsonPath('data.path', '/article/'.$published->slug);

        $draft = Article::where('statut', 'brouillon')->firstOrFail();
        $mapping = WordpressImport::where('entity', 'article')->where('target_id', $draft->id)->firstOrFail();
        $this->getJson('/api/v2/legacy/resolve?'.http_build_query(['path' => $mapping->original_path]))->assertNotFound();
        $this->getJson('/api/v2/articles/'.$draft->slug)->assertNotFound();
        $this->getJson('/api/v2/legacy/resolve?path=//evil.example/')->assertUnprocessable();
    }

    public function test_future_pages_are_hidden_until_publication(): void
    {
        Page::create(['titre' => 'Test futur', 'slug' => 'integration-future-page', 'contenu' => '<p>Test</p>', 'statut' => 'publie', 'date_publication' => now()->addDay()]);
        $this->getJson('/api/v2/pages/integration-future-page')->assertNotFound();
    }

    public function test_flash_infos_show_recent_public_articles_when_no_manual_flash_exists(): void
    {
        FlashInfo::query()->delete();
        PublicCache::forgetFlashInfos();
        $future = Article::publies()->firstOrFail()->replicate();
        $future->forceFill(['slug' => 'integration-future-flash', 'date_publication' => now()->addDay()])->save();
        $draft = Article::where('statut', 'brouillon')->firstOrFail();

        $response = $this->getJson('/api/v2/flash-infos')->assertOk()->assertJsonCount(5, 'data');
        $ids = array_column($response->json('data'), 'article_id');
        $this->assertNotContains($future->id, $ids);
        $this->assertNotContains($draft->id, $ids);
        foreach ($response->json('data') as $flash) {
            $this->assertNotEmpty($flash['texte']);
            $this->assertNotEmpty($flash['article']['slug']);
        }
    }

    public function test_manual_active_flash_infos_take_priority_and_hide_unpublished_articles(): void
    {
        FlashInfo::query()->delete();
        FlashInfo::create(['texte' => 'Flash éditorial', 'est_actif' => true]);
        FlashInfo::create(['texte' => 'Flash expiré', 'est_actif' => true, 'date_fin' => now()->subDay()]);
        FlashInfo::create(['texte' => 'Flash brouillon', 'est_actif' => true, 'article_id' => Article::where('statut', 'brouillon')->firstOrFail()->id]);
        PublicCache::forgetFlashInfos();
        $this->getJson('/api/v2/flash-infos')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.texte', 'Flash éditorial');
    }

    public function test_article_changes_invalidate_the_automatic_flash_cache(): void
    {
        FlashInfo::query()->delete();
        PublicCache::forgetFlashInfos();
        $first = $this->getJson('/api/v2/flash-infos')->assertOk()->json('data.0.article_id');
        Article::findOrFail($first)->update(['titre' => 'Actualité corrigée dans le bandeau']);
        PublicCache::forgetArticles();
        $this->getJson('/api/v2/flash-infos')->assertOk()
            ->assertJsonPath('data.0.texte', 'Actualité corrigée dans le bandeau');
    }

    public function test_the_cms_can_create_and_edit_multiple_categories_without_losing_legacy_relations(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        $categories = Categorie::limit(2)->pluck('id')->all();
        $response = $this->postJson('/api/v2/admin/articles', [
            'titre' => 'Test rubriques multiples', 'slug' => 'integration-multiple-categories',
            'extrait' => 'Extrait', 'contenu' => '<p>Article de test</p>', 'statut' => 'brouillon',
            'categorie_id' => $categories[0], 'categorie_ids' => [$categories[1]],
        ])->assertCreated();
        $id = $response->json('article.id');
        $this->assertEqualsCanonicalizing($categories, Article::findOrFail($id)->categories()->pluck('categories.id')->all());
        // Un ancien client qui ne connaît pas categorie_ids préserve les rubriques.
        $this->putJson('/api/v2/admin/articles/'.$id, ['titre' => 'Titre corrigé'])->assertOk();
        $this->assertCount(2, Article::findOrFail($id)->categories);
        $this->putJson('/api/v2/admin/articles/'.$id, ['categorie_ids' => []])->assertOk();
        $this->assertSame([$categories[0]], Article::findOrFail($id)->categories()->pluck('categories.id')->all());
    }

    public function test_a_second_import_keeps_counts_and_local_editorial_changes(): void
    {
        $article = Article::firstOrFail();
        $article->update(['titre' => 'Correction éditoriale locale']);
        $counts = [Article::count(), Categorie::count(), WordpressImport::count()];
        $this->artisan('wordpress:import', ['--batch' => 500])->assertSuccessful();
        $this->assertSame($counts, [Article::count(), Categorie::count(), WordpressImport::count()]);
        $this->assertSame('Correction éditoriale locale', $article->fresh()->titre);
    }
}
