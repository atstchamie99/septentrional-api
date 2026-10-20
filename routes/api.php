<?php

use App\Http\Controllers\Api\AuthController;

// Admin Controllers
use App\Http\Controllers\Api\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Api\Admin\CategorieController as AdminCategorieController;
use App\Http\Controllers\Api\Admin\CommentaireController as AdminCommentaireController;
use App\Http\Controllers\Api\Admin\FlashInfoController as AdminFlashInfoController;
use App\Http\Controllers\Api\Admin\PageController as AdminPageController;
use App\Http\Controllers\Api\Admin\ParametreController as AdminParametreController;
use App\Http\Controllers\Api\Admin\VideoController as AdminVideoController;
use App\Http\Controllers\Api\Admin\PartenaireController as AdminPartenaireController;
use App\Http\Controllers\Api\Admin\AbonneController as AdminAbonneController;
use App\Http\Controllers\Api\Admin\ImageController;
use App\Http\Controllers\Api\Admin\MediaLibraryController;
use App\Http\Controllers\Api\Admin\OccasionSpecialeController as AdminOccasionSpecialeController;
use App\Http\Controllers\Api\Admin\NewsletterController as AdminNewsletterController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ApiKeyController as AdminApiKeyController;

// Public Controllers
use App\Http\Controllers\Api\Public\ArticleController as PublicArticleController;
use App\Http\Controllers\Api\Public\CategorieController as PublicCategorieController;
use App\Http\Controllers\Api\Public\CommentaireController as PublicCommentaireController;
use App\Http\Controllers\Api\Public\FlashInfoController as PublicFlashInfoController;
use App\Http\Controllers\Api\Public\PageController as PublicPageController;
use App\Http\Controllers\Api\Public\VideoController as PublicVideoController;
use App\Http\Controllers\Api\Public\PartenaireController as PublicPartenaireController;
use App\Http\Controllers\Api\Public\AbonneController as PublicAbonneController;
use App\Http\Controllers\Api\Public\ParametreController as PublicParametreController;
use App\Http\Controllers\Api\Public\OccasionSpecialeController as PublicOccasionSpecialeController;
use App\Http\Controllers\Api\Public\MeteoController as PublicMeteoController;
use App\Http\Controllers\Api\Public\SeoPreviewController;
use App\Http\Controllers\Api\Public\SitemapController;

use Illuminate\Support\Facades\Route;

Route::prefix('v2')->name('v2.')->group(function () {

    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'app'    => 'Septentrional API',
        'version'=> 'v2',
        'time'   => now()->toIso8601String(),
        'php_upload' => [
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'loaded_ini' => php_ini_loaded_file(),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'file_uploads' => ini_get('file_uploads'),
            'upload_tmp_dir' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
        ],
    ]))->name('health');

    // Routes volontairement EXCLUES de l'exigence de clé API : elles sont
    // atteintes directement par un client qui ne peut pas poser de header
    // personnalisé (crawler lisant robots.txt, <a href>/<embed> vers un PDF),
    // jamais via le client axios du SPA qui, lui, envoie toujours X-API-Key.
    Route::get('/sitemap.xml', SitemapController::class)->name('seo.sitemap');

    // Toutes les routes ci-dessous (publiques ET admin) exigent une clé API
    // valide (header X-API-Key), en plus de l'auth Sanctum pour les routes admin.
    Route::middleware('api-key')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | API Publique
    |--------------------------------------------------------------------------
    */

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle-api-login')
        ->name('login');

    Route::post('/login/verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle-api-otp')
        ->name('login.verify-otp');

    Route::post('/login/send-email-otp', [AuthController::class, 'sendEmailOtp'])
        ->middleware('throttle-api-login');

    Route::post('/reauth/send-email-otp', [AuthController::class, 'sendReauthEmailOtp'])
        ->middleware('throttle-api-login');

    Route::post('/reauth/verify-otp', [AuthController::class, 'reverifyOtp'])
        ->middleware('throttle-api-otp')
        ->name('reauth.verify-otp');

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle-api-login')
        ->name('password.email');

    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle-api-otp')
        ->name('password.update');

    // SEO : pages HTML lisibles par les robots sociaux et sitemap dynamique.
    Route::get('/seo/articles/{slug}', [SeoPreviewController::class, 'article'])
        ->name('seo.articles');
    Route::get('/seo/videos/{slug}', [SeoPreviewController::class, 'video'])
        ->name('seo.videos');
    Route::get('/seo/pages/{slug}', [SeoPreviewController::class, 'page'])
        ->name('seo.pages');
    Route::get('/seo/categories/{slug}', [SeoPreviewController::class, 'category'])
        ->name('seo.categories');

    // Catégories
    Route::get('/legacy/resolve', \App\Http\Controllers\Api\Public\LegacyUrlController::class)->name('legacy.resolve');

    Route::get('/categories', [PublicCategorieController::class, 'index'])->name('categories.index');
    Route::get('/categories/{slug}', [PublicCategorieController::class, 'show'])->name('categories.show');

    // Articles
    Route::get('/articles', [PublicArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/alertes', [PublicArticleController::class, 'alertes'])->name('articles.alertes');
    Route::get('/articles/populaires', [PublicArticleController::class, 'populaires'])->name('articles.populaires');
    Route::get('/articles/archives-mois', [PublicArticleController::class, 'archivesMois'])->name('articles.archives-mois');
    Route::get('/articles/{slug}', [PublicArticleController::class, 'show'])->name('articles.show');

    // Commentaires
    Route::post('/commentaires', [PublicCommentaireController::class, 'store'])
        ->middleware('throttle-api-comments')
        ->name('commentaires.store');
    Route::get('/articles/{articleSlug}/commentaires', [PublicCommentaireController::class, 'parArticle'])->name('articles.commentaires');

    // Flash Infos
    Route::get('/flash-infos', [PublicFlashInfoController::class, 'index'])->name('flash-infos.index');

    // Pages
    Route::get('/pages/{slug}', [PublicPageController::class, 'show'])->name('pages.show');

    // Vidéos
    Route::get('/videos', [PublicVideoController::class, 'index'])->name('videos.index');
    Route::get('/videos/{slug}', [PublicVideoController::class, 'show'])->name('videos.show');

    // Partenaires
    Route::get('/partenaires', [PublicPartenaireController::class, 'index'])->name('partenaires.index');
    Route::get('/partenaires/position/{position}', [PublicPartenaireController::class, 'parPosition'])->name('partenaires.position');

    // Paramètres publics (social, general, seo uniquement)
    Route::get('/parametres/publics', [PublicParametreController::class, 'publics'])->name('parametres.publics');

    // Occasion spéciale active du jour + météo (section sous Flash Info)
    Route::get('/occasions/active', [PublicOccasionSpecialeController::class, 'active'])->name('occasions.active');
    Route::get('/meteo/jour', [PublicMeteoController::class, 'jour'])->name('meteo.jour');

    // Newsletter
    Route::post('/abonnes', [PublicAbonneController::class, 'store'])
        ->middleware('throttle-api-newsletter')
        ->name('abonnes.store');
    Route::get('/abonnes/desabonner/{jeton}', [PublicAbonneController::class, 'desabonner'])->name('abonnes.desabonner');

    /*
    |--------------------------------------------------------------------------
    | API Protégée (Auth Sanctum)
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::put('/me/password', [AuthController::class, 'changePassword'])->name('me.password');

        // Rédacteurs + admins : création/modification/lecture du contenu, jamais la suppression (réservée à l'admin ci-dessous).
        Route::get('/admin/dashboard/stats', [DashboardController::class, 'stats'])->name('admin.dashboard.stats');

        Route::middleware(['role:admin,redacteur'])->prefix('admin')->name('admin.')->group(function () {

            Route::apiResource('articles', AdminArticleController::class)->except(['destroy']);
            Route::patch('/articles/{article}/publier', [AdminArticleController::class, 'publier'])->name('articles.publier');
            Route::patch('/articles/{article}/archiver', [AdminArticleController::class, 'archiver'])->name('articles.archiver');

            Route::get('/commentaires', [AdminCommentaireController::class, 'index'])->name('commentaires.index');
            Route::get('/commentaires/en-attente', [AdminCommentaireController::class, 'enAttente'])->name('commentaires.en-attente');
            Route::patch('/commentaires/{commentaire}/moderer', [AdminCommentaireController::class, 'moderer'])->name('commentaires.moderer');

            Route::apiResource('flash-infos', AdminFlashInfoController::class)->except(['destroy']);
            Route::apiResource('videos', AdminVideoController::class)->except(['destroy']);
            Route::patch('/videos/{video}/publier', [AdminVideoController::class, 'publier'])->name('videos.publier');

            Route::apiResource('occasions', AdminOccasionSpecialeController::class)
                ->parameters(['occasions' => 'occasion'])
                ->except(['destroy']);

            // Pages : consultation autorisée aux administrateurs et rédacteurs
            Route::get('/pages', [AdminPageController::class, 'index'])
                ->name('pages.index');
            
            Route::get('/pages/{page}', [AdminPageController::class, 'show'])
                ->name('pages.show');

            // Upload d'images pour Tiptap
            Route::post('/upload-image', [ImageController::class, 'uploadImage'])->name('images.upload');

            // Upload de vidéos pour Tiptap
            Route::post('/upload-video', [ImageController::class, 'uploadVideo'])->name('videos.upload');

            Route::get('/media-library', [MediaLibraryController::class, 'index'])->name('media-library.index');
            Route::post('/media-library', [MediaLibraryController::class, 'store'])
                ->middleware('trusted-cookie-origin')
                ->name('media-library.store');
            Route::get('/media-library/{id}', [MediaLibraryController::class, 'show'])->name('media-library.show');
        });

        // Admin uniquement : suppressions de contenu + tout le reste de "Système"/"Gestion" (Catégories, Partenaires,
        // Paramètres, Abonnés, Newsletter, gestion des comptes).
        Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {

            Route::delete('/articles/{article}', [AdminArticleController::class, 'destroy'])->name('articles.destroy');
            Route::delete('/commentaires/{commentaire}', [AdminCommentaireController::class, 'destroy'])->name('commentaires.destroy');
            Route::delete('/flash-infos/{flashInfo}', [AdminFlashInfoController::class, 'destroy'])->name('flash-infos.destroy');
            Route::delete('/videos/{video}', [AdminVideoController::class, 'destroy'])->name('videos.destroy');
            Route::delete('/occasions/{occasion}', [AdminOccasionSpecialeController::class, 'destroy'])->name('occasions.destroy');
            Route::delete('/media-library/{id}', [MediaLibraryController::class, 'destroy'])
                ->middleware('trusted-cookie-origin')
                ->name('media-library.destroy');
            Route::post('/media-library/bulk-delete', [MediaLibraryController::class, 'destroyMany'])
                ->middleware('trusted-cookie-origin')
                ->name('media-library.bulk-delete');
            Route::post('/pages', [AdminPageController::class, 'store'])
                ->name('pages.store');

            Route::match(['put', 'patch'], '/pages/{page}', [AdminPageController::class, 'update'])
                ->name('pages.update');

            Route::delete('/pages/{page}', [AdminPageController::class, 'destroy'])
                ->name('pages.destroy');

            Route::apiResource('categories', AdminCategorieController::class);
            Route::apiResource('partenaires', AdminPartenaireController::class);

            Route::apiResource('parametres', AdminParametreController::class);
            Route::get('/parametres/groupe/{groupe}', [AdminParametreController::class, 'parGroupe'])->name('parametres.groupe');

            Route::get('/abonnes', [AdminAbonneController::class, 'index'])->name('abonnes.index');
            Route::get('/abonnes/{abonne}', [AdminAbonneController::class, 'show'])->name('abonnes.show');
            Route::delete('/abonnes/{abonne}', [AdminAbonneController::class, 'destroy'])->name('abonnes.destroy');

            Route::post('/newsletter/envoyer', [AdminNewsletterController::class, 'envoyer'])
                ->middleware('throttle-api-newsletter-send')
                ->name('newsletter.envoyer');

            Route::apiResource('utilisateurs', AdminUserController::class)->except(['destroy']);
            Route::patch('/utilisateurs/{utilisateur}/suspendre', [AdminUserController::class, 'suspendre'])->name('utilisateurs.suspendre');
            Route::patch('/utilisateurs/{utilisateur}/reactiver', [AdminUserController::class, 'reactiver'])->name('utilisateurs.reactiver');

            Route::get('/api-keys', [AdminApiKeyController::class, 'index'])->name('api-keys.index');
            Route::post('/api-keys', [AdminApiKeyController::class, 'store'])->name('api-keys.store');
            Route::delete('/api-keys/{apiKey}', [AdminApiKeyController::class, 'destroy'])->name('api-keys.destroy');
        });
    });

    }); // fin du groupe api-key
});
