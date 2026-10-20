<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSlug;
use App\Traits\InvalidatesMediaUsageCache;

class Page extends Model
{
    use HasFactory, HasSlug, InvalidatesMediaUsageCache;

    protected $fillable = [
        'titre',
        'slug',
        'contenu',
        'statut',
        'meta_titre',
        'meta_description',
        'date_publication',
    ];

    protected function casts(): array
    {
        return ['date_publication' => 'datetime'];
    }

    public function scopePublies($query)
    {
        return $query->where('statut', 'publie')->where(function ($dates) {
            $dates->whereNull('date_publication')->orWhere('date_publication', '<=', now());
        });
    }
}
