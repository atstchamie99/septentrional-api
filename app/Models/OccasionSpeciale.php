<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\InvalidatesMediaUsageCache;

class OccasionSpeciale extends Model
{
    use HasFactory, InvalidatesMediaUsageCache;

    protected $table = 'occasions_speciales';

    protected $fillable = [
        'titre',
        'texte',
        'image',
        'date_debut',
        'date_fin',
        'est_actif',
    ];

    protected $casts = [
        'est_actif'  => 'boolean',
        'date_debut' => 'date',
        'date_fin'   => 'date',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    public function scopeActives($query)
    {
        return $query->where('est_actif', true)
            ->whereDate('date_debut', '<=', now())
            ->whereDate('date_fin', '>=', now());
    }
}
