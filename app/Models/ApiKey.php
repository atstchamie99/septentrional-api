<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'name',
        'prefix',
        'key_hash',
        'created_by',
        'is_active',
        'last_used_at',
        'revoked_at',
        'revoked_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'last_used_at' => 'datetime',
            'revoked_at'   => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Génère une nouvelle clé API et retourne à la fois le modèle persisté
     * (sans le secret) et la clé en clair (à afficher une seule fois à l'admin).
     *
     * @return array{key: ApiKey, plainText: string}
     */
    public static function generate(string $name, ?int $createdBy): array
    {
        $secret = Str::random(40);
        $prefix = 'sept_' . Str::lower(Str::random(10));
        $plainText = $prefix . '.' . $secret;

        $key = static::create([
            'name'       => $name,
            'prefix'     => $prefix,
            'key_hash'   => hash('sha256', $plainText),
            'created_by' => $createdBy,
            'is_active'  => true,
        ]);

        return ['key' => $key, 'plainText' => $plainText];
    }

    /**
     * Retrouve une clé active à partir de sa valeur en clair fournie par le client.
     * Comparaison en temps constant sur le hash pour limiter le risque de timing attack.
     */
    public static function findValid(string $plainText): ?self
    {
        $prefix = Str::before($plainText, '.');

        if ($prefix === $plainText) {
            return null;
        }

        $candidate = static::query()
            ->where('prefix', $prefix)
            ->where('is_active', true)
            ->first();

        if (! $candidate) {
            return null;
        }

        $expectedHash = hash('sha256', $plainText);

        return hash_equals($candidate->key_hash, $expectedHash) ? $candidate : null;
    }
}
