<?php
// app/Models/Article.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSlug;
use App\Traits\InvalidatesMediaUsageCache;
use App\Traits\Publiable;

class Article extends Model
{
    use HasFactory, HasSlug, InvalidatesMediaUsageCache, Publiable;

    protected $fillable = [
        'user_id',
        'redacteur_nom',
        'categorie_id',
        'titre',
        'slug',
        'extrait',
        'contenu',
        'image_principale',
        'statut',
        'est_alerte',
        'nombre_vues',
        'meta_titre',
        'meta_description',
        'date_publication',
    ];

    protected $casts = [
        'est_alerte'       => 'boolean',
        'nombre_vues'      => 'integer',
        'date_publication' => 'datetime',
    ];

    // ========== RELATIONS ==========
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }

    public function etiquettes()
    {
        return $this->belongsToMany(Etiquette::class, 'article_etiquette');
    }

    public function categories()
    {
        return $this->belongsToMany(Categorie::class, 'article_categorie');
    }

    public function scopeDansCategorie($query, string $slug)
    {
        return $query->where(function ($categories) use ($slug) {
            $categories->whereHas('categorie', fn ($q) => $q->where('slug', $slug))
                ->orWhereHas('categories', fn ($q) => $q->where('slug', $slug));
        });
    }

    public function commentaires()
    {
        return $this->morphMany(Commentaire::class, 'commentable');
    }

    public function medias()
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    // ========== HELPERS ==========
    public function incrementerVues(): void
    {
        static::query()
            ->whereKey($this->getKey())
            ->toBase()
            ->increment('nombre_vues');

        $this->nombre_vues = (int) $this->nombre_vues + 1;
        $this->syncOriginalAttribute('nombre_vues');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_principale) {
            return null;
        }

        if (str_starts_with($this->image_principale, 'http://') || str_starts_with($this->image_principale, 'https://')) {
            return $this->image_principale;
        }

        return asset('storage/' . ltrim($this->image_principale, '/'));
    }
}
