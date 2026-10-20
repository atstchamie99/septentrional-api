<?php

namespace Tests\Unit;

use App\Http\Middleware\SecureHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Tests\TestCase;

class SecureHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.frontend_url' => 'https://frontend.example']);
    }

    public function test_seo_routes_keep_their_public_cache_headers(): void
    {
        foreach (['v2.seo.articles', 'v2.seo.videos', 'v2.seo.sitemap'] as $routeName) {
            $response = $this->throughMiddleware(
                $routeName,
                'public, max-age=300, stale-while-revalidate=600'
            );

            $cacheHeader = (string) $response->headers->get('Cache-Control');
            $this->assertStringContainsString('public', $cacheHeader);
            $this->assertStringContainsString('max-age=300', $cacheHeader);
            $this->assertStringContainsString('stale-while-revalidate=600', $cacheHeader);
            $this->assertStringNotContainsString('no-store', $cacheHeader);
            $this->assertFalse($response->headers->has('Pragma'));
            $this->assertFalse($response->headers->has('Expires'));
        }
    }

    public function test_other_api_routes_remain_non_cacheable(): void
    {
        $response = $this->throughMiddleware('v2.articles.index', 'public, max-age=300');

        $cacheHeader = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheHeader);
        $this->assertStringContainsString('no-cache', $cacheHeader);
        $this->assertStringContainsString('must-revalidate', $cacheHeader);
        $this->assertStringContainsString('private', $cacheHeader);
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }



    private function throughMiddleware(string $routeName, string $cacheControl): Response
    {
        $request = Request::create('/api/v2/test', 'GET');
        $route = (new Route('GET', '/api/v2/test', fn () => null))->name($routeName);
        $request->setRouteResolver(fn () => $route);

        return (new SecureHeaders)->handle(
            $request,
            fn () => new Response('test', 200, ['Cache-Control' => $cacheControl])
        );
    }
}
