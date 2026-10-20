<?php

namespace App\Services\Wordpress;

use App\Models\Article;
use App\Models\Categorie;
use App\Models\Commentaire;
use App\Models\Etiquette;
use App\Models\MediaLibrary;
use App\Models\Page;
use App\Models\Parametre;
use App\Models\User;
use App\Models\WordpressImport;
use App\Support\PublicCache;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WordpressImporter
{
    private Connection $source;

    private string $prefix;

    private string $sourceKey;

    private string $siteUrl;

    private array $report = [];

    private array $created = [];

    public function __construct(private WordpressContent $content) {}

    public function configure(string $connection, string $prefix): void
    {
        if (! preg_match('/^[a-zA-Z0-9_]+$/D', $prefix)) {
            throw new InvalidArgumentException('Le préfixe WordPress doit contenir uniquement des lettres, chiffres et underscores.');
        }
        if ($connection === DB::getDefaultConnection()) {
            throw new InvalidArgumentException('La connexion source doit être distincte de la connexion cible.');
        }
        $this->source = DB::connection($connection);
        $target = DB::connection();
        if ($this->source->getDatabaseName() === $target->getDatabaseName()
            && $this->source->getConfig('host') === $target->getConfig('host')
            && $this->source->getConfig('port') === $target->getConfig('port')) {
            throw new InvalidArgumentException('Les bases source et cible doivent être différentes.');
        }
        $this->prefix = $prefix;
        foreach (['posts', 'postmeta', 'users', 'terms', 'term_taxonomy', 'term_relationships', 'comments', 'options'] as $table) {
            if (! $this->source->getSchemaBuilder()->hasTable($prefix.$table)) {
                throw new InvalidArgumentException("Table WordPress manquante : {$prefix}{$table}");
            }
        }
        $site = $this->table('options')->where('option_name', 'home')->value('option_value');
        $this->siteUrl = rtrim($this->content->url($site ?: config('wordpress.site_url'), config('wordpress.site_url'))
            ?? config('wordpress.site_url'), '/');
        $this->sourceKey = 'wordpress:'.hash('sha256', $this->siteUrl);
        $this->report = [];
        $this->created = [];
    }

    public function inventory(): array
    {
        return [
            'auteurs' => $this->table('users')->count(),
            'categories' => $this->table('term_taxonomy')->where('taxonomy', 'category')->count(),
            'etiquettes' => $this->table('term_taxonomy')->where('taxonomy', 'post_tag')->count(),
            'images' => $this->table('posts')->where('post_type', 'attachment')->whereIn('post_mime_type', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])->count(),
            'articles' => $this->posts('post')->count(),
            'pages' => $this->posts('page')->count(),
            'livres_pdf' => $this->posts(['3d-flip-book', 'dflip'])->count(),
            'galeries_video' => $this->posts('wpgp_youtube_gallery')->count(),
            'commentaires' => $this->table('comments')->whereIn('comment_approved', ['0', '1'])->whereIn('comment_type', ['', 'comment'])->count(),
            'types_speciaux_a_revoir' => $this->table('posts')->whereNotIn('post_type', ['post', 'page', 'attachment', 'revision', 'nav_menu_item', 'oembed_cache', '3d-flip-book', 'dflip', 'wpgp_youtube_gallery', 'wp_global_styles', 'tt_font_control'])->whereNotIn('post_status', ['auto-draft', 'trash'])->count(),
        ];
    }

    public function import(int $batchSize = 100): array
    {
        $this->importUsers($batchSize);
        $this->importTaxonomies($batchSize);
        $this->importImages($batchSize);
        $this->importPosts($batchSize);
        $this->importBooks($batchSize);
        $this->importGalleries($batchSize);
        $this->importComments($batchSize);
        $this->importSettings();
        PublicCache::forgetArticles();
        PublicCache::forgetParametres();
        MediaLibrary::forgetUsageCache();

        return $this->report;
    }

    private function table(string $name): Builder
    {
        return $this->source->table($this->prefix.$name);
    }

    private function posts(string|array $type): Builder
    {
        return $this->table('posts')->whereIn('post_type', (array) $type)
            ->whereNotIn('post_status', ['auto-draft', 'trash']);
    }

    private function mapping(string $entity, int $id): ?WordpressImport
    {
        return WordpressImport::where('source', $this->sourceKey)->where('entity', $entity)->where('wordpress_id', $id)->first();
    }

    private function targetId(string $entity, int $id): ?int
    {
        return $this->mapping($entity, $id)?->target_id;
    }

    private function persist(string $entity, int $id, callable $create, ?string $url = null, array $metadata = []): ?Model
    {
        $mapping = $this->mapping($entity, $id);
        if ($mapping) {
            // Une relance préserve les corrections éditoriales faites après l'import.
            $this->report[$entity.':existants'] = ($this->report[$entity.':existants'] ?? 0) + 1;

            return null;
        }

        return DB::transaction(function () use ($entity, $id, $create, $url, $metadata) {
            $model = $create();
            WordpressImport::create([
                'source' => $this->sourceKey, 'entity' => $entity, 'wordpress_id' => $id,
                'target_type' => $model::class, 'target_id' => $model->getKey(),
                'original_url' => $url,
                'original_path' => $url && parse_url($url, PHP_URL_PATH) !== '/' ? '/'.trim((string) parse_url($url, PHP_URL_PATH), '/').'/' : null,
                'metadata' => $metadata, 'imported_at' => now(),
            ]);
            $this->created[$entity][$id] = $model->getKey();
            $this->report[$entity.':importes'] = ($this->report[$entity.':importes'] ?? 0) + 1;

            return $model;
        });
    }

    private function meta(int $id): array
    {
        // Seules les métadonnées utiles sont lues (pas les CSS de plugins ni secrets).
        return $this->table('postmeta')->where('post_id', $id)->whereIn('meta_key', [
            '_thumbnail_id', '_wp_attached_file', '_wp_attachment_image_alt', '_wp_old_slug',
            'rank_math_title', 'rank_math_description', 'rank_math_primary_category',
            '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_primary_category',
            'color_newsmagazine_wpb_post_views_count', 'post_views_count', '3dfb_type', '3dfb_data', '_dflip_data', '_wpgp_page_options',
        ])->pluck('meta_value', 'meta_key')->all();
    }

    private function uniqueSlug(string $model, string $slug, int $wordpressId): string
    {
        $slug = trim($slug) ?: 'wordpress-'.$wordpressId;
        $slug = Str::limit($slug, 180, '');
        $candidate = $slug;
        $suffix = 0;
        while ($model::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-wp-'.$wordpressId.($suffix ? '-'.$suffix : '');
            $suffix++;
        }

        return $candidate;
    }

    private function importUsers(int $size): void
    {
        $this->table('users')->orderBy('ID')->chunkById($size, function ($rows) {
            foreach ($rows as $row) {
                $this->persist('user', $row->ID, function () use ($row) {
                    $email = filter_var($row->user_email, FILTER_VALIDATE_EMAIL) ? $row->user_email : "wordpress-{$row->ID}@invalid.example";
                    if (User::where('email', $email)->exists()) {
                        $email = 'wordpress-'.Str::random(12).'@invalid.example';
                    }
                    $user = new User;
                    $user->forceFill([
                        'name' => $this->content->text($row->display_name ?: $row->user_login),
                        'email' => $email, 'password' => Str::random(64),
                        'role' => 'redacteur', 'is_active' => false,
                        'created_at' => $this->content->date($row->user_registered) ?? now(),
                    ])->save();

                    return $user;
                });
            }
        }, 'ID');
    }

    private function importTaxonomies(int $size): void
    {
        $this->table('term_taxonomy')->whereIn('taxonomy', ['category', 'post_tag'])
            ->chunkById($size, function ($rows) {
                foreach ($rows as $row) {
                    $term = $this->table('terms')->where('term_id', $row->term_id)->first();
                    if (! $term) {
                        continue;
                    }
                    $entity = $row->taxonomy === 'category' ? 'category' : 'tag';
                    $model = $entity === 'category' ? Categorie::class : Etiquette::class;
                    $this->persist($entity, $term->term_id, fn () => $model::create([
                        'nom' => $this->content->text($term->name),
                        'slug' => $this->uniqueSlug($model, $term->slug, $term->term_id),
                        ...($entity === 'category' ? ['description' => $this->content->text($row->description, 5000), 'couleur' => '#2C9CF2'] : []),
                    ]), $this->siteUrl.'/'.($entity === 'category' ? 'category/' : 'tag/').$term->slug.'/', ['parent' => $row->parent]);
                }
            }, 'term_taxonomy_id');

        // Deuxième passage : les parents peuvent avoir un ID supérieur à l'enfant.
        $this->table('term_taxonomy')->where('taxonomy', 'category')->where('parent', '>', 0)
            ->chunkById($size, function ($rows) {
                foreach ($rows as $row) {
                    $id = $this->targetId('category', $row->term_id);
                    $parent = $this->targetId('category', $row->parent);
                    if (isset($this->created['category'][$row->term_id]) && $id && $parent && $id !== $parent) {
                        Categorie::whereKey($id)->whereNull('parent_id')->update(['parent_id' => $parent]);
                    }
                }
            }, 'term_taxonomy_id');
    }

    private function importImages(int $size): void
    {
        $this->posts('attachment')->whereIn('post_mime_type', ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
            ->chunkById($size, function ($rows) {
                foreach ($rows as $row) {
                    $meta = $this->meta($row->ID);
                    $url = $this->attachmentUrl($row, $meta);
                    if (! $url) {
                        $this->report['image:urls_invalides'] = ($this->report['image:urls_invalides'] ?? 0) + 1;

                        continue;
                    }
                    $this->persist('image', $row->ID, function () use ($row, $meta, $url) {
                        $media = new MediaLibrary;
                        $media->forceFill([
                            'uploaded_by' => $this->targetId('user', $row->post_author),
                            'type' => 'image', 'source' => 'external', 'external_url' => $url,
                            'original_name' => $this->content->text(basename(parse_url($url, PHP_URL_PATH))),
                            'mime_type' => $row->post_mime_type, 'title' => $this->content->text($row->post_title),
                            'alt_text' => $this->content->text($meta['_wp_attachment_image_alt'] ?? ''),
                            'created_at' => $this->content->date($row->post_date_gmt, $row->post_date) ?? now(),
                        ])->save();

                        return $media;
                    }, $url);
                }
            }, 'ID');
    }

    private function attachmentUrl(object $row, array $meta): ?string
    {
        $file = $meta['_wp_attached_file'] ?? '';
        if ($file !== '' && ! str_contains($file, '..') && ! str_contains($file, '://') && ! str_starts_with($file, '/')) {
            return $this->content->url($this->siteUrl.'/wp-content/uploads/'.$file, $this->siteUrl);
        }

        return $this->content->url($row->guid, $this->siteUrl);
    }

    private function importPosts(int $size): void
    {
        $this->posts(['post', 'page'])->chunkById($size, function ($rows) {
            foreach ($rows as $row) {
                $meta = $this->meta($row->ID);
                $isArticle = $row->post_type === 'post';
                $entity = $isArticle ? 'article' : 'page';
                $model = $isArticle ? Article::class : Page::class;
                $date = $this->content->date($row->post_date_gmt, $row->post_date);
                $originalUrl = $this->siteUrl.($isArticle && $date ? '/'.substr($date, 0, 4).'/'.substr($date, 5, 2).'/'.substr($date, 8, 2) : '').'/'.$row->post_name.'/';
                $this->persist($entity, $row->ID, function () use ($row, $meta, $isArticle, $model, $date) {
                    $status = $this->content->status($row->post_status, $row->post_password !== '');
                    $attributes = [
                        'titre' => $this->content->text($row->post_title),
                        'slug' => $this->uniqueSlug($model, $row->post_name, $row->ID),
                        'contenu' => $this->content->html($row->post_content),
                        'statut' => $status, 'date_publication' => $date,
                        'meta_titre' => $this->content->text($meta['rank_math_title'] ?? $meta['_yoast_wpseo_title'] ?? $row->post_title),
                        'meta_description' => $this->content->text($meta['rank_math_description'] ?? $meta['_yoast_wpseo_metadesc'] ?? $row->post_excerpt, 500),
                        'created_at' => $date ?? now(),
                        'updated_at' => $this->content->date($row->post_modified_gmt, $row->post_modified) ?? $date ?? now(),
                    ];
                    if (! $isArticle) {
                        $page = new Page;
                        $page->forceFill($attributes)->save();

                        return $page;
                    }
                    $terms = $this->table('term_relationships')->join($this->prefix.'term_taxonomy as taxonomy', 'taxonomy.term_taxonomy_id', '=', $this->prefix.'term_relationships.term_taxonomy_id')
                        ->where('object_id', $row->ID)->select(['taxonomy.term_id', 'taxonomy.taxonomy'])->get();
                    $categoryIds = $terms->where('taxonomy', 'category')->pluck('term_id')->map(fn ($id) => $this->targetId('category', $id))->filter()->values()->all();
                    $primary = $this->targetId('category', (int) ($meta['rank_math_primary_category'] ?? $meta['_yoast_wpseo_primary_category'] ?? 0));
                    if (! in_array($primary, $categoryIds, true)) {
                        $primary = $categoryIds[0] ?? $this->fallbackCategory();
                    }
                    $authorId = $this->targetId('user', $row->post_author) ?? $this->fallbackAuthor();
                    $image = $this->targetId('image', (int) ($meta['_thumbnail_id'] ?? 0));
                    $article = new Article;
                    $article->forceFill([
                        ...$attributes, 'user_id' => $authorId, 'categorie_id' => $primary,
                        'redacteur_nom' => User::find($authorId)?->name,
                        'extrait' => $this->content->text($row->post_excerpt ?: $row->post_content, 500),
                        'image_principale' => $image ? MediaLibrary::find($image)?->external_url : null,
                        'nombre_vues' => min(2147483647, max(0, (int) ($meta['color_newsmagazine_wpb_post_views_count'] ?? $meta['post_views_count'] ?? 0))),
                        'est_alerte' => $terms->where('taxonomy', 'category')->contains(fn ($term) => Categorie::find($this->targetId('category', $term->term_id))?->slug === 'a-la-une'),
                    ])->save();
                    $article->categories()->sync(array_unique([...$categoryIds, $primary]));
                    $article->etiquettes()->sync($terms->where('taxonomy', 'post_tag')->pluck('term_id')->map(fn ($id) => $this->targetId('tag', $id))->filter()->all());

                    return $article;
                }, $originalUrl, [
                    'post_type' => $row->post_type, 'post_status' => $row->post_status,
                    'old_slug' => $meta['_wp_old_slug'] ?? null,
                    'original_content' => $row->post_content,
                ]);
            }
        }, 'ID');
    }

    private function fallbackCategory(): int
    {
        return Categorie::firstOrCreate(['slug' => 'non-classe'], ['nom' => 'Non classé', 'couleur' => '#2C9CF2'])->id;
    }

    private function fallbackAuthor(): int
    {
        return User::firstOrCreate(['email' => 'archives@invalid.example'], [
            'name' => 'Archives Septentrional', 'password' => Str::random(64), 'role' => 'redacteur', 'is_active' => false,
        ])->id;
    }

    private function importBooks(int $size): void
    {
        $this->posts(['3d-flip-book', 'dflip'])->chunkById($size, function ($rows) {
            foreach ($rows as $row) {
                $meta = $this->meta($row->ID);
                $book = $this->content->array($meta['3dfb_data'] ?? $meta['_dflip_data'] ?? '');
                $url = $this->content->url($book['guid'] ?? $book['pdf_source'] ?? null, $this->siteUrl);
                $html = $row->post_content;
                if ($url) {
                    $html .= '<p><a href="'.htmlspecialchars($url, ENT_QUOTES, 'UTF-8').'">Consulter le document PDF</a></p>';
                } else {
                    $this->report['livre:documents_a_verifier'] = ($this->report['livre:documents_a_verifier'] ?? 0) + 1;
                }
                $this->persist('book', $row->ID, function () use ($row, $html) {
                    $page = new Page;
                    $page->forceFill([
                        'titre' => $this->content->text($row->post_title),
                        'slug' => $this->uniqueSlug(Page::class, $row->post_name, $row->ID),
                        'contenu' => $this->content->html($html),
                        'statut' => $this->content->status($row->post_status, $row->post_password !== ''),
                        'date_publication' => $this->content->date($row->post_date_gmt, $row->post_date),
                    ])->save();

                    return $page;
                }, $this->siteUrl.'/'.$row->post_type.'/'.$row->post_name.'/', ['post_type' => $row->post_type, 'book_data' => $book, 'original_content' => $row->post_content]);
            }
        }, 'ID');
    }

    private function importGalleries(int $size): void
    {
        $this->posts('wpgp_youtube_gallery')->chunkById($size, function ($rows) {
            foreach ($rows as $row) {
                $settings = $this->content->array($this->meta($row->ID)['_wpgp_page_options'] ?? '');
                $playlist = $settings['wpgpyt_playlist_id'] ?? '';
                $html = $row->post_content;
                if (is_string($playlist) && preg_match('/^[a-zA-Z0-9_-]{10,100}$/D', $playlist)) {
                    $html .= '<iframe src="https://www.youtube.com/embed/videoseries?list='.$playlist.'" width="640" height="360" allowfullscreen></iframe>';
                }
                $this->persist('gallery', $row->ID, function () use ($row, $html) {
                    $page = new Page;
                    $page->forceFill([
                        'titre' => $this->content->text($row->post_title),
                        'slug' => $this->uniqueSlug(Page::class, $row->post_name, $row->ID),
                        'contenu' => $this->content->html($html),
                        'statut' => $this->content->status($row->post_status, $row->post_password !== ''),
                        'date_publication' => $this->content->date($row->post_date_gmt, $row->post_date),
                    ])->save();

                    return $page;
                }, $this->siteUrl.'/'.$row->post_type.'/'.$row->post_name.'/', ['playlist' => $playlist]);
            }
        }, 'ID');
    }

    private function importComments(int $size): void
    {
        $this->table('comments')->whereIn('comment_approved', ['0', '1'])->whereIn('comment_type', ['', 'comment'])
            ->chunkById($size, function ($rows) {
                foreach ($rows as $row) {
                    $articleId = $this->targetId('article', $row->comment_post_ID);
                    if (! $articleId) {
                        $this->report['comment:hors_articles'] = ($this->report['comment:hors_articles'] ?? 0) + 1;

                        continue;
                    }
                    $this->persist('comment', $row->comment_ID, function () use ($row, $articleId) {
                        $comment = new Commentaire;
                        $comment->forceFill([
                            'user_id' => $this->targetId('user', $row->user_id),
                            'nom' => $this->content->text($row->comment_author),
                            'email' => filter_var($row->comment_author_email, FILTER_VALIDATE_EMAIL) ? $row->comment_author_email : null,
                            'commentable_type' => Article::class, 'commentable_id' => $articleId,
                            'contenu' => $this->content->text($row->comment_content, 65000),
                            'statut' => $row->comment_approved === '1' ? 'approuve' : 'en_attente',
                            'created_at' => $this->content->date($row->comment_date_gmt, $row->comment_date) ?? now(),
                        ])->save();

                        return $comment;
                    }, null, ['parent' => $row->comment_parent]);
                }
            }, 'comment_ID');
        WordpressImport::where('source', $this->sourceKey)->where('entity', 'comment')
            ->chunkById($size, function ($rows) {
                foreach ($rows as $mapping) {
                    $parent = $this->targetId('comment', (int) ($mapping->metadata['parent'] ?? 0));
                    if (isset($this->created['comment'][$mapping->wordpress_id]) && $parent && $parent !== $mapping->target_id) {
                        $comment = Commentaire::find($mapping->target_id);
                        $parentComment = Commentaire::find($parent);
                        if ($comment && $parentComment && $comment->commentable_id === $parentComment->commentable_id) {
                            $comment->update(['parent_id' => $parent]);
                        }
                    }
                }
            });
    }

    private function importSettings(): void
    {
        $theme = $this->content->array((string) $this->table('options')->where('option_name', 'theme_mods_color-newsmagazine')->value('option_value'));
        foreach ($theme['top_header_social_links_items'] ?? [] as $item) {
            foreach (['facebook', 'youtube', 'twitter', 'instagram', 'tiktok', 'whatsapp'] as $network) {
                $url = $this->content->url($item['link'] ?? null, $this->siteUrl);
                if (str_contains($item['font'] ?? '', $network) && $url) {
                    Parametre::firstOrCreate(['cle' => $network], ['valeur' => $url, 'type' => 'string', 'groupe' => 'social']);
                }
            }
        }
        $description = $this->table('options')->where('option_name', 'blogdescription')->value('option_value');
        foreach (['site_name' => 'Septentrional', 'site_description' => $this->content->text($description ?: 'Actualités du Togo'), 'site_url' => $this->siteUrl, 'primary_color' => '#2C9CF2'] as $key => $value) {
            Parametre::firstOrCreate(['cle' => $key], ['valeur' => $value, 'type' => 'string', 'groupe' => 'general']);
        }
    }
}
