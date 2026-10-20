<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Categorie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_otp_challenge_for_staff(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v2/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'requires_otp',
                'challenge_token'
            ])
            ->assertJson(['requires_otp' => true]);
    }

    public function test_public_comment_cannot_bypass_identity_with_user_id(): void
    {
        $response = $this->postJson('/api/v2/commentaires', [
            'user_id' => 1,
            'contenu' => 'Message sans identité',
            'commentable_type' => Article::class,
            'commentable_id' => 1,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nom', 'email']);
    }

    public function test_public_comment_content_is_stored_as_plain_text(): void
    {
        $author = User::factory()->create([
            'role' => 'redacteur',
            'is_active' => true,
        ]);

        $categorie = Categorie::create([
            'nom' => 'Actualités',
            'slug' => 'actualites',
        ]);

        $article = Article::create([
            'user_id' => $author->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Article publié',
            'slug' => 'article-publie',
            'extrait' => 'Extrait',
            'contenu' => 'Contenu',
            'statut' => 'publie',
            'date_publication' => now()->subMinute(),
        ]);

        $this->postJson('/api/v2/commentaires', [
            'nom' => '<b>Jean</b>',
            'email' => 'jean@example.com',
            'contenu' => '<script>alert(1)</script>Bonjour',
            'commentable_type' => Article::class,
            'commentable_id' => $article->id,
        ])->assertCreated();

        $this->assertDatabaseHas('commentaires', [
            'nom' => 'Jean',
            'contenu' => 'alert(1)Bonjour',
        ]);
    }

    public function test_svg_partner_logo_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]));

        $response = $this->postJson('/api/v2/admin/partenaires', [
            'nom' => 'Partenaire',
            'logo' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
            'position' => 'pied-de-page',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_redacteur_cannot_manage_admin_only_pages(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'redacteur',
            'is_active' => true,
        ]));

        $this->postJson('/api/v2/admin/pages', [
            'titre' => 'Page',
            'contenu' => 'Contenu',
            'statut' => 'publie',
        ])->assertForbidden();
    }

    public function test_article_update_rejects_non_library_public_media_paths_for_image_reference(): void
    {
        Storage::fake('public');
        $writer = User::factory()->create([
            'role' => 'redacteur',
            'is_active' => true,
        ]);
        Sanctum::actingAs($writer);

        $victimPath = 'uploads/victim-article.mp3';
        Storage::disk('public')->put($victimPath, 'audio');
        $categorie = Categorie::create([
            'nom' => 'Culture',
            'slug' => 'culture',
        ]);
        $article = Article::create([
            'user_id' => $writer->id,
            'categorie_id' => $categorie->id,
            'titre' => 'Article attaquant',
            'slug' => 'article-attaquant',
            'extrait' => 'Extrait',
            'contenu' => 'Contenu',
            'statut' => 'brouillon',
        ]);

        $this->putJson("/api/v2/admin/articles/{$article->id}", [
            'image_principale_url' => "https://example.test/storage/{$victimPath}",
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image_principale_url']);

        Storage::disk('public')->assertExists($victimPath);
    }

    public function test_password_change_revokes_current_token_and_forces_relogin(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'google2fa_enabled' => true,
            'google2fa_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        ]);

        $token = $user->createToken('device', ['*'], now()->addDay())->plainTextToken;

        // Mot de passe à forte entropie (pas un exemple de tutoriel) : la
        // politique impose Password::uncompromised(), qui rejette tout mot
        // de passe déjà présent dans une fuite de données connue (vérifié
        // via l'API k-anonymity de HaveIBeenPwned) — 'NewPassword123!' en
        // fait partie et ferait échouer ce test pour une mauvaise raison.
        $newPassword = 'Kz9!'.bin2hex(random_bytes(6)).'#Qw';

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v2/me/password', [
                'current_password' => 'password',
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertOk()
            ->assertCookieExpired('auth_token')
            ->assertCookieExpired('otp_reauth');

        app('auth')->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v2/me')
            ->assertUnauthorized();
    }
}
