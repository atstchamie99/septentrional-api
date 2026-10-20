<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Abonne extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'email',
        'statut',
        'jeton',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->jeton)) {
                $model->jeton = Str::random(32);
            }
        });
    }

    public function scopeActifs($query)
    {
        return $query->where('statut', 'actif');
    }
}