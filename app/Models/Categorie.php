<?php
// app/Models/Categorie.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSlug;

class Categorie extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = [
        'parent_id',
        'nom',
        'slug',
        'description',
        'couleur',
        'icone',
        'ordre',
    ];

    // ========== RELATIONS ==========
    public function parent()
    {
        return $this->belongsTo(Categorie::class, 'parent_id');
    }

    public function enfants()
    {
        return $this->hasMany(Categorie::class, 'parent_id');
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function videos()
    {
        return $this->hasMany(Video::class);
    }

    public function articlesAssocies()
    {
        return $this->belongsToMany(Article::class, 'article_categorie');
    }



    // ========== SCOPES ==========
    public function scopeRacines($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActives($query)
    {
        return $query->whereHas('articles', function ($q) {
            $q->publies();
        })->orWhereHas('videos', function ($q) {
            $q->publies();
        });
    }
}
