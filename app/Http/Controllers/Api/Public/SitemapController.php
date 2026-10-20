<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Categorie;
use App\Models\Page;
use App\Models\Video;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = Cache::remember('v1:seo:sitemap', now()->addHour(), function (): array {
            $entries = collect([
                $this->entry('/'),
                $this->entry('/actualites'),
                $this->entry('/videos'),
                $this->entry('/politique'),
                $this->entry('/autres-rubriques'),
                $this->entry('/a-propos'),
                $this->entry('/contact'),
                $this->entry('/mentions-legales'),
            ]);

            Article::publies()
                ->select(['slug', 'updated_at'])
                ->orderBy('id')
                ->each(fn (Article $article) => $entries->push(
                    $this->entry("/article/{$article->slug}", $article->updated_at?->toAtomString()),
                ));

            Video::publies()
                ->select(['slug', 'updated_at'])
                ->orderBy('id')
                ->each(fn (Video $video) => $entries->push(
                    $this->entry("/video/{$video->slug}", $video->updated_at?->toAtomString()),
                ));

            Categorie::query()
                ->select(['id', 'slug', 'updated_at'])
                ->where(function ($query): void {
                    $query
                        ->whereHas('articles', fn ($articles) => $articles->publies())
                        ->orWhereHas('videos', fn ($videos) => $videos->publies());
                })
                ->orderBy('id')
                ->each(fn (Categorie $category) => $entries->push(
                    $this->entry("/categorie/{$category->slug}", $category->updated_at?->toAtomString()),
                ));

            Page::publies()
                ->select(['slug', 'updated_at'])
                ->orderBy('id')
                ->each(function (Page $page) use ($entries): void {
                    $path = in_array($page->slug, ['a-propos', 'contact', 'mentions-legales'], true)
                        ? "/{$page->slug}"
                        : "/pages/{$page->slug}";

                    $entries->push(
                        $this->entry($path, $page->updated_at?->toAtomString()),
                    );
                });

            return $entries->unique('location')->values()->all();
        });

        return response()
            ->view('seo.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=3600, stale-while-revalidate=86400');
    }

    private function entry(string $path, ?string $lastModified = null): array
    {
        return [
            'location' => rtrim((string) config('app.frontend_url'), '/')
                . '/'
                . ltrim($path, '/'),
            'lastModified' => $lastModified,
        ];
    }
}
