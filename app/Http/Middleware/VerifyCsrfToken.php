<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Exclure toutes les routes API de la validation CSRF
        // car on utilise des cookies HttpOnly avec Sanctum
        'api/*',
        'sanctum/*',
    ];
}
