<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Categorie;
use App\Models\Page;
use App\Models\Video;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class SeoPreviewController extends Controller
{
    public function article(string $slug): Response
    {
        $article = Article::publies()
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->preview([
            'type' => 'article',
            'title' => $this->usableTitle($article->meta_titre, $article->titre),
            'description' => $article->meta_description ?: $article->extrait,
            'content' => $article->contenu,
            'image' => $article->image_url,
            'canonicalUrl' => $this->frontendUrl("/article/{$article->slug}"),
            'publishedTime' => $article->date_publication?->toIso8601String(),
            'modifiedTime' => $article->updated_at?->toIso8601String(),
            'schemaType' => 'NewsArticle',
        ]);
    }

    public function video(string $slug): Response
    {
        $video = Video::publies()
            ->where('slug', $slug)
            ->firstOrFail();

        return $this->preview([
            'type' => 'video.other',
            'title' => $video->titre,
            'description' => $video->description
                ?: "Regardez {$video->titre} sur Septentrional.",
            'image' => $video->miniature_url,
            'canonicalUrl' => $this->frontendUrl("/video/{$video->slug}"),
            'publishedTime' => $video->date_publication?->toIso8601String(),
            'modifiedTime' => $video->updated_at?->toIso8601String(),
            'schemaType' => 'VideoObject',
        ]);
    }

    public function page(string $slug): Response
    {
        $page = Page::publies()
            ->where('slug', $slug)
            ->firstOrFail();

        $path = in_array($page->slug, ['a-propos', 'contact', 'mentions-legales'], true)
            ? "/{$page->slug}"
            : "/pages/{$page->slug}";

        return $this->preview([
            'type' => 'website',
            'title' => $page->titre,
            'description' => $page->contenu,
            'image' => null,
            'canonicalUrl' => $this->frontendUrl($path),
            'publishedTime' => null,
            'modifiedTime' => $page->updated_at?->toIso8601String(),
            'schemaType' => 'WebPage',
        ]);
    }

    public function category(string $slug): Response
    {
        $category = Categorie::query()
            ->where('slug', $slug)
            ->where(function ($query): void {
                $query
                    ->whereHas('articles', fn ($articles) => $articles->publies())
                    ->orWhereHas('videos', fn ($videos) => $videos->publies());
            })
            ->firstOrFail();

        return $this->preview([
            'type' => 'website',
            'title' => $category->nom,
            'description' => $category->description
                ?: "Retrouvez les contenus {$category->nom} de Septentrional.",
            'image' => null,
            'canonicalUrl' => $this->frontendUrl("/categorie/{$category->slug}"),
            'publishedTime' => null,
            'modifiedTime' => $category->updated_at?->toIso8601String(),
            'schemaType' => 'CollectionPage',
        ]);
    }

    private function preview(array $data): Response
    {
        $data['siteName'] = 'Septentrional';
        $data['title'] = Str::limit(trim(strip_tags((string) $data['title'])), 100, '');
        $data['description'] = Str::limit(
            preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $data['description']))),
            200,
            '…',
        );
        $data['content'] = Str::limit(
            preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) ($data['content'] ?? '')))),
            30000,
            '',
        );
        $data['image'] = $data['image'] ?: null;
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => $data['schemaType'],
            'name' => $data['title'],
            'headline' => $data['title'],
            'description' => $data['description'],
            'url' => $data['canonicalUrl'],
            ...($data['image'] ? ['image' => $data['image']] : []),
            'datePublished' => $data['publishedTime'],
            'dateModified' => $data['modifiedTime'],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'Septentrional',
                'url' => $this->frontendUrl('/'),
            ],
        ];

        if ($data['schemaType'] === 'NewsArticle') {
            $structuredData['mainEntityOfPage'] = [
                '@type' => 'WebPage',
                '@id' => $data['canonicalUrl'],
            ];
            $structuredData['author'] = [
                '@type' => 'Organization',
                'name' => 'Septentrional',
            ];
            $structuredData['isAccessibleForFree'] = true;

            if ($data['content'] !== '') {
                $structuredData['articleBody'] = $data['content'];
            }
        }

        $data['structuredDataJson'] = json_encode(
            $structuredData,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP,
        );

        return response()
            ->view('seo.preview', $data)
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=600');
    }

    private function frontendUrl(string $path): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . '/' . ltrim($path, '/');
    }

    private function usableTitle(?string $metaTitle, string $fallback): string
    {
        if (! $metaTitle || str_contains($metaTitle, '%%')) {
            return $fallback;
        }

        $title = preg_replace(
            '/\s*\|\s*Septentrional\s*$/iu',
            '',
            trim(strip_tags($metaTitle)),
        );

        return $title !== '' ? $title : $fallback;
    }
}
