<?php

namespace App\Models;

use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Models\Video;
use App\Models\OccasionSpeciale;
use App\Models\Partenaire;
use App\Models\Page;

class MediaLibrary extends Model
{
    protected $table = 'media_library';
    private const USAGE_CACHE_KEY = 'media_library.used_ids';
    protected $fillable = ['uploaded_by', 'type', 'source', 'path', 'external_url', 'original_name', 'mime_type', 'size', 'title', 'alt_text', 'processing', 'processing_path', 'processing_error'];
    protected $casts = ['size' => 'integer', 'processing' => 'boolean'];
    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        return $this->source === 'external' ? $this->external_url : ($this->path ? Storage::disk('public')->url($this->path) : null);
    }

    public static function selectedImagePath(int $id): string
    {
        $media = static::query()
            ->whereKey($id)
            ->where('type', 'image')
            ->first();

        if (! $media) {
            throw ValidationException::withMessages([
                'image_media_id' => 'Le média sélectionné doit être une image disponible dans la médiathèque.',
            ]);
        }

        if ($media->source === 'external' && $media->external_url) {
            return $media->external_url;
        }

        if ($media->path) {
            return $media->path;
        }

        throw ValidationException::withMessages([
            'image_media_id' => 'Le média sélectionné ne possède pas un chemin valide.',
        ]);
    }

    public static function isManagedPath(?string $path): bool
    {
        return is_string($path) && $path !== ''
            && static::query()->where('source', 'upload')->where('path', $path)->exists();
    }

    public static function forgetUsageCache(): void
    {
        Cache::forget(self::USAGE_CACHE_KEY);
    }

    /** @return array<int, int> */
    public static function usedMediaIds(): array
    {
        return Cache::remember(self::USAGE_CACHE_KEY, now()->addMinutes(5), function (): array {
            $pathToIds = [];
            $urlToIds = [];

            static::query()
                ->select(['id', 'path', 'external_url'])
                ->get()
                ->each(function (MediaLibrary $media) use (&$pathToIds, &$urlToIds): void {
                    if ($media->path) {
                        $pathToIds[$media->path][] = $media->id;
                    }
                    if ($media->external_url) {
                        $urlToIds[$media->external_url][] = $media->id;
                    }
                });

            $usedIds = [];
            $markValue = function (?string $value) use (&$usedIds, $pathToIds, $urlToIds): void {
                if (! is_string($value) || $value === '') {
                    return;
                }

                foreach (($pathToIds[$value] ?? []) as $id) {
                    $usedIds[$id] = $id;
                }
                foreach (($urlToIds[$value] ?? []) as $id) {
                    $usedIds[$id] = $id;
                }
            };

            foreach ([
                [Article::class, 'image_principale'],
                [Emission::class, 'image'],
                [Episode::class, 'image'],
                [Video::class, 'miniature'],
                [Video::class, 'url_video'],
                [OccasionSpeciale::class, 'image'],
                [Partenaire::class, 'logo'],
            ] as [$model, $column]) {
                $model::query()
                    ->whereNotNull($column)
                    ->where($column, '<>', '')
                    ->distinct()
                    ->pluck($column)
                    ->each($markValue);
            }

            foreach ([Article::class, Page::class] as $model) {
                $model::query()
                    ->where('contenu', 'like', '%/storage/%')
                    ->pluck('contenu')
                    ->each(function (?string $content) use (&$usedIds, $pathToIds): void {
                        foreach (self::storagePathsFromContent($content) as $path) {
                            foreach (($pathToIds[$path] ?? []) as $id) {
                                $usedIds[$id] = $id;
                            }
                        }
                    });
            }

            return array_values($usedIds);
        });
    }

    /** @return array<int, string> */
    private static function storagePathsFromContent(?string $content): array
    {
        if (! is_string($content) || $content === '') {
            return [];
        }

        preg_match_all("/\/storage\/([^\"'<>\\s)]+)/i", $content, $matches);

        return array_values(array_unique(array_filter(array_map(function (string $path): string {
            $path = strtok($path, '?#') ?: $path;
            return rawurldecode(ltrim($path, '/'));
        }, $matches[1] ?? []))));
    }

    /** @return array<string, int> */
    public static function referencesForPath(string $path): array
    {
        return static::referencesForPaths([$path])[$path] ?? [];
    }

    /** @return array<string, int> */
    public static function referencesForExternalUrl(string $url): array
    {
        $references = [];

        foreach ([
            'articles' => [Article::class, 'image_principale'],
            'emissions' => [Emission::class, 'image'],
            'episodes' => [Episode::class, 'image'],
            'videos' => [Video::class, 'url_video'],
            'occasions' => [OccasionSpeciale::class, 'image'],
            'partenaires' => [Partenaire::class, 'logo'],
        ] as $name => [$model, $column]) {
            $count = $model::query()->where($column, $url)->count();
            if ($count) $references[$name] = $count;
        }

        $like = '%' . addcslashes($url, '%_\\') . '%';
        $articleCount = Article::query()->where('contenu', 'like', $like)->count();
        $pageCount = Page::query()->where('contenu', 'like', $like)->count();
        if ($articleCount) $references['contenu_articles'] = $articleCount;
        if ($pageCount) $references['contenu_pages'] = $pageCount;

        return $references;
    }

    /** @param array<int, string> $paths
     *  @return array<string, array<string, int>> */
    public static function referencesForPaths(array $paths): array
    {
        $paths = array_values(array_unique(array_filter($paths)));
        $references = array_fill_keys($paths, []);

        foreach ([
            'articles' => [Article::class, 'image_principale'],
            'emissions' => [Emission::class, 'image'],
            'episodes' => [Episode::class, 'image'],
            'videos' => [Video::class, 'miniature'],
            'occasions' => [OccasionSpeciale::class, 'image'],
            'partenaires' => [Partenaire::class, 'logo'],
        ] as $name => [$model, $column]) {
            $model::query()->whereIn($column, $paths)->selectRaw("{$column} as path, count(*) as count")
                ->groupBy($column)->pluck('count', 'path')->each(function ($count, $path) use (&$references, $name): void {
                    $references[$path][$name] = (int) $count;
                });
        }

        // Images inserted in rich-text article or page content must also be protected.
        foreach ($paths as $path) {
            $needle = '%/storage/' . str_replace(['%', '_'], ['\\%', '\\_'], $path) . '%';
            $articleCount = Article::query()->where('contenu', 'like', $needle)->count();
            $pageCount = Page::query()->where('contenu', 'like', $needle)->count();
            if ($articleCount) $references[$path]['contenu_articles'] = $articleCount;
            if ($pageCount) $references[$path]['contenu_pages'] = $pageCount;
        }

        return $references;
    }

    /**
     * Comme referencesForPath(), mais exclut un enregistrement précis de la
     * recherche. Sert à décider si l'ANCIEN chemin d'une image sur le point
     * d'être remplacée (upload direct) peut être supprimé du disque sans
     * casser une autre utilisation (image piochée dans la médiathèque et
     * réutilisée ailleurs, ou insérée dans le corps d'un autre article/page) :
     * sans cette exclusion, l'enregistrement en cours de modification se
     * compterait toujours lui-même (son ancienne valeur est encore en base
     * tant que la transaction n'a pas été validée) et l'ancien fichier ne
     * serait donc jamais considéré comme supprimable.
     *
     * @return array<string, int>
     */
    public static function referencesForPathExcludingSelf(string $path, string $excludeModel, int $excludeId): array
    {
        $references = [];

        foreach ([
            'articles' => [Article::class, 'image_principale'],
            'emissions' => [Emission::class, 'image'],
            'episodes' => [Episode::class, 'image'],
            'videos' => [Video::class, 'miniature'],
            'occasions' => [OccasionSpeciale::class, 'image'],
            'partenaires' => [Partenaire::class, 'logo'],
        ] as $name => [$model, $column]) {
            $query = $model::query()->where($column, $path);
            if ($model === $excludeModel) {
                $query->where('id', '!=', $excludeId);
            }
            $count = $query->count();
            if ($count) $references[$name] = $count;
        }

        $needle = '%/storage/' . str_replace(['%', '_'], ['\\%', '\\_'], $path) . '%';

        $articleQuery = Article::query()->where('contenu', 'like', $needle);
        if ($excludeModel === Article::class) {
            $articleQuery->where('id', '!=', $excludeId);
        }
        $articleCount = $articleQuery->count();
        if ($articleCount) $references['contenu_articles'] = $articleCount;

        $pageQuery = Page::query()->where('contenu', 'like', $needle);
        if ($excludeModel === Page::class) {
            $pageQuery->where('id', '!=', $excludeId);
        }
        $pageCount = $pageQuery->count();
        if ($pageCount) $references['contenu_pages'] = $pageCount;

        return $references;
    }

    /**
     * Détermine si l'ancien chemin d'une image remplacée par upload direct
     * peut être physiquement supprimé du disque, à passer tel quel en second
     * argument de MediaReplacement::replaceOnDisk(). Centralise la règle de
     * sécurité (ne jamais supprimer un fichier encore utilisé ailleurs) pour
     * que chaque contrôleur (Article, Émission, Épisode, Vidéo, Occasion
     * spéciale, Partenaire) l'applique de la même façon.
     *
     * Chaque upload via OptimizedImageStorage s'auto-indexe dans media_library
     * (source=upload) : si le fichier est jugé supprimable, on retire aussi
     * cette ligne ici, sinon elle resterait à pointer vers un fichier absent
     * du disque (vignette cassée dans la médiathèque).
     */
    public static function safeOldPathForReplacement(
        ?string $oldPath,
        string $newPath,
        string $excludeModel,
        int $excludeId,
    ): ?string {
        if (! $oldPath || $oldPath === $newPath) {
            return null;
        }

        if (static::referencesForPathExcludingSelf($oldPath, $excludeModel, $excludeId) !== []) {
            return null;
        }

        static::query()->where('source', 'upload')->where('path', $oldPath)->delete();

        return $oldPath;
    }
}
