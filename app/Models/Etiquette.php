<?php
// app/Models/Etiquette.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasSlug;

class Etiquette extends Model
{
    use HasFactory, HasSlug;

    protected $fillable = ['nom', 'slug'];

    public function articles()
    {
        return $this->belongsToMany(Article::class, 'article_etiquette');
    }
}