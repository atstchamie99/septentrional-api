<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FlashInfo extends Model
{
    use HasFactory;

    protected $table = 'flash_infos';

    protected $fillable = [
        'texte',
        'article_id',
        'est_actif',
        'date_debut',
        'date_fin',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    protected $casts = [
        'est_actif'   => 'boolean',
        'date_debut'  => 'datetime',
        'date_fin'    => 'datetime',
    ];

    public function scopeActifs($query)
    {
        return $query->where('est_actif', true)
            ->where(function ($q) {
                $q->whereNull('date_debut')
                  ->orWhere('date_debut', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('date_fin')
                  ->orWhere('date_fin', '>=', now());
            });
    }
}