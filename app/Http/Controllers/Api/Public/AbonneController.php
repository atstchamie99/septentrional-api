<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreAbonneRequest;
use App\Models\Abonne;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AbonneController extends Controller
{
    public function store(StoreAbonneRequest $request): JsonResponse
    {
        Abonne::create([
            ...$request->validated(),
            'statut' => 'actif',
        ]);

        return ApiResponse::success(null, 'Inscription à la newsletter réussie.', 201);
    }

    public function desabonner(string $jeton): JsonResponse
    {
        $abonne = Abonne::where('jeton', $jeton)->firstOrFail();
        $abonne->update(['statut' => 'desabonne']);

        return ApiResponse::success(null, 'Vous êtes désabonné de la newsletter.');
    }
}
