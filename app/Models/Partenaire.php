<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\InvalidatesMediaUsageCache;

class Partenaire extends Model
{
    use HasFactory, InvalidatesMediaUsageCache;

    protected $fillable = [
        'nom',
        'logo',
        'lien',
        'position',
        'date_debut',
        'date_fin',
        'est_actif',
    ];

    protected $casts = [
        'est_actif'  => 'boolean',
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public function getLogoUrlAttribute(): string
    {
        return asset('storage/' . $this->logo);
    }

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