<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $table = 'medias';

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'chemin_fichier',
        'type',
        'taille',
        'type_mime',
        'texte_alternatif',
    ];

    protected $casts = [
        'taille' => 'integer',
    ];

    public function mediable()
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->chemin_fichier);
    }
}