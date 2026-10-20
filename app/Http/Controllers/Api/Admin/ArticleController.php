<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\MediaLibrary;
use App\Services\OptimizedImageStorage;
use App\Support\AuditLog;
use App\Support\MediaReplacement;
use App\Support\PublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

class ArticleController extends Controller
{
    public function index(ListRequest $request)
    {
        $query = Article::with(['categorie', 'categories', 'user', 'etiquettes'])->latest();

        return ArticleResource::collection($query->paginate($request->getPerPage(20)));
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['image_media_id'], $data['image_principale_url'], $data['remove_image']);
        $data['contenu'] = Purifier::clean($data['contenu']);

        if ($request->hasFile('image_principale')) {
            $data['image_principale'] = app(OptimizedImageStorage::class)
                ->store($request->file('image_principale'), 'articles/images');
        } elseif ($request->filled('image_media_id')) {
            $data['image_principale'] = MediaLibrary::selectedImagePath($request->integer('image_media_id'));
        } elseif ($request->filled('image_principale_url')) {
            $data['image_principale'] = $this->extractStoragePath($request->input('image_principale_url'));
        }

        $data['user_id'] = $request->user()->id;
        $categoryIds = $data['categorie_ids'] ?? [];
        $tagIds = $data['etiquette_ids'] ?? [];
        unset($data['categorie_ids'], $data['etiquette_ids']);
        $article = DB::transaction(function () use ($data, $categoryIds, $tagIds) {
            $article = Article::create($data);
            $article->categories()->sync(array_unique([...$categoryIds, $article->categorie_id]));
            $article->etiquettes()->sync($tagIds);

            return $article;
        });

        PublicCache::forgetArticles();

        return response()->json([
            'message' => 'Article créé avec succès.',
            'article' => new ArticleResource($article->load('categorie', 'categories', 'etiquettes')),
        ], 201);
    }

    public function show(Article $article)
    {
        $this->authorize('view', $article);

        return new ArticleResource($article->load(['categorie', 'categories', 'user', 'etiquettes', 'commentaires']));
    }

    public function update(UpdateArticleRequest $request, Article $article): JsonResponse
    {
        $this->authorize('update', $article);

        $data = $request->validated();
        unset($data['image_media_id'], $data['image_principale_url'], $data['remove_image']);

        if (isset($data['contenu'])) {
            $data['contenu'] = Purifier::clean($data['contenu']);
        }

        $hasCategoryIds = array_key_exists('categorie_ids', $data);
        $categoryIds = $data['categorie_ids'] ?? [];
        unset($data['categorie_ids']);
        $hasEtiquetteIds = array_key_exists('etiquette_ids', $data);
        $etiquetteIds = $data['etiquette_ids'] ?? null;
        unset($data['etiquette_ids']);

        MediaReplacement::run(function (MediaReplacement $media) use (
            $request,
            $article,
            &$data,
            $hasEtiquetteIds,
            $etiquetteIds,
            $hasCategoryIds,
            $categoryIds,
        ): void {
            if ($request->hasFile('image_principale')) {
                $newImage = app(OptimizedImageStorage::class)
                    ->store($request->file('image_principale'), 'articles/images');
                $data['image_principale'] = $media->replaceOnDisk(
                    $newImage,
                    MediaLibrary::safeOldPathForReplacement($article->image_principale, $newImage, Article::class, $article->id),
                );
            } elseif ($request->filled('image_media_id')) {
                $data['image_principale'] = MediaLibrary::selectedImagePath($request->integer('image_media_id'));
            } elseif ($request->filled('image_principale_url')) {
                $data['image_principale'] = $this->extractStoragePath($request->input('image_principale_url'));
            } elseif ($request->boolean('remove_image')) {
                $data['image_principale'] = null;
            }

            DB::transaction(function () use (
                $article,
                $data,
                $hasEtiquetteIds,
                $etiquetteIds,
                $hasCategoryIds,
                $categoryIds,
            ): void {
                $article->fill($data)->saveOrFail();

                if ($hasCategoryIds) {
                    $article->categories()->sync(array_unique([...$categoryIds, $article->categorie_id]));
                } elseif (array_key_exists('categorie_id', $data)) {
                    $article->categories()->syncWithoutDetaching([$article->categorie_id]);
                }

                if ($hasEtiquetteIds && is_array($etiquetteIds)) {
                    $article->etiquettes()->sync($etiquetteIds);
                }
            });
        });

        PublicCache::forgetArticles();

        return response()->json([
            'message' => 'Article mis à jour.',
            'article' => new ArticleResource($article->fresh()->load('categorie', 'categories', 'etiquettes')),
        ]);
    }

    public function destroy(Article $article): JsonResponse
    {
        $this->authorize('delete', $article);

        if ($article->image_principale && ! MediaLibrary::isManagedPath($article->image_principale)) {
            Storage::disk('public')->delete($article->image_principale);
        }

        $article->etiquettes()->detach();
        $article->delete();

        PublicCache::forgetArticles();

        AuditLog::record('Article supprimé', ['article_id' => $article->id, 'titre' => $article->titre]);

        return response()->json(['message' => 'Article supprimé.']);
    }

    public function publier(Article $article): JsonResponse
    {
        $this->authorize('publier', $article);

        $article->update([
            'statut' => 'publie',
            'date_publication' => now(),
        ]);

        PublicCache::forgetArticles();

        return response()->json([
            'message' => 'Article publié.',
            'article' => new ArticleResource($article),
        ]);
    }

    public function archiver(Article $article): JsonResponse
    {
        $this->authorize('archiver', $article);

        $article->update(['statut' => 'archive']);

        PublicCache::forgetArticles();

        return response()->json([
            'message' => 'Article archivé.',
            'article' => new ArticleResource($article),
        ]);
    }

    private function extractStoragePath(string $url): string
    {
        $path = ! str_starts_with($url, 'http')
            ? ltrim($url, '/')
            : ltrim((string) preg_replace('#^/storage/#', '', (string) (parse_url($url, PHP_URL_PATH) ?: $url)), '/');

        if (! MediaLibrary::query()
            ->where('source', 'upload')
            ->where('type', 'image')
            ->where('path', $path)
            ->exists()) {
            throw ValidationException::withMessages([
                'image_principale_url' => 'L’image référencée doit provenir de la médiathèque.',
            ]);
        }

        return $path;
    }
}
