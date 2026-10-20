<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Parametre extends Model
{
    use HasFactory;

    protected $fillable = ['cle', 'valeur', 'type', 'groupe'];

    protected $casts = [
        'valeur' => 'string',
    ];

    public function getValeurCasteeAttribute(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->valeur, FILTER_VALIDATE_BOOLEAN),
            'json'    => json_decode($this->valeur, true),
            default   => $this->valeur,
        };
    }

    public static function get(string $cle, mixed $default = null): mixed
    {
        $param = self::where('cle', $cle)->first();
        return $param?->valeur_castee ?? $default;
    }

    public static function set(string $cle, mixed $valeur, string $type = 'string', string $groupe = 'general'): void
    {
        self::updateOrCreate(
            ['cle' => $cle],
            [
                'valeur' => is_array($valeur) ? json_encode($valeur) : (string) $valeur,
                'type'   => $type,
                'groupe' => $groupe,
            ]
        );
    }
}