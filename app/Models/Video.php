<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSlug;
use App\Traits\InvalidatesMediaUsageCache;
use App\Traits\Publiable;

class Video extends Model
{
    use HasFactory, HasSlug, InvalidatesMediaUsageCache, Publiable;

    protected $fillable = [
        'user_id',
        'categorie_id',
        'titre',
        'slug',
        'description',
        'miniature',
        'url_video',
        'type_video',
        'duree',
        'statut',
        'nombre_vues',
        'date_publication',
    ];

    protected $casts = [
        'duree'            => 'integer',
        'nombre_vues'      => 'integer',
        'date_publication' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categorie()
    {
        return $this->belongsTo(Categorie::class);
    }

    public function commentaires()
    {
        return $this->morphMany(Commentaire::class, 'commentable');
    }

    public function getMiniatureUrlAttribute(): ?string
    {
        return $this->miniature ? asset('storage/' . $this->miniature) : null;
    }

    public function getEmbedUrlAttribute(): string
    {
        return match ($this->type_video) {
            'youtube' => 'https://www.youtube.com/embed/' . $this->extractYoutubeId(),
            'vimeo'   => 'https://player.vimeo.com/video/' . basename(parse_url($this->url_video, PHP_URL_PATH)),
            default   => $this->url_video ? asset($this->url_video) : '',
        };
    }

    private function extractYoutubeId(): string
    {
        preg_match('/(?:v=|\/)([0-9A-Za-z_-]{11})/', $this->url_video, $matches);
        return $matches[1] ?? '';
    }

    public function incrementerVues(): void
    {
        static::query()
            ->whereKey($this->getKey())
            ->toBase()
            ->increment('nombre_vues');

        $this->nombre_vues = (int) $this->nombre_vues + 1;
        $this->syncOriginalAttribute('nombre_vues');
    }
}
