<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Video;
use App\Policies\ArticlePolicy;
use App\Policies\VideoPolicy;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    protected $policies = [
        Article::class  => ArticlePolicy::class,
        Video::class    => VideoPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        Password::defaults(fn () => Password::min(8)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());

        RateLimiter::for('api-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('api-otp', fn (Request $request) => Limit::perMinute(5)->by($request->ip() . '|' . $request->input('challenge_token')));
        RateLimiter::for('api-comments', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('api-newsletter', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('api-newsletter-send', fn (Request $request) => Limit::perHour(5)->by($request->user()?->id ?: $request->ip()));
        // Limiteur par défaut : 120 req/min en dev, 60 req/min en prod
        $apiLimit = app()->isProduction() ? 60 : 120;
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute($apiLimit)->by($request->ip()));

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(SecurityScheme::http('bearer'));
            });

        Scramble::registerApi('v2', [
            'info' => [
                'title'   => 'Septentrional API',
                'version' => '2.0.0',
            ],
            'api_path' => 'api/v2',
        ]);
    }
}
