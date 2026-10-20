<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreParametreRequest;
use App\Http\Requests\Admin\UpdateParametreRequest;
use App\Http\Resources\ParametreResource;
use App\Models\Parametre;
use App\Support\PublicCache;
use Illuminate\Http\JsonResponse;

class ParametreController extends Controller
{
    public function index()
    {
        return ParametreResource::collection(Parametre::orderBy('groupe')->get());
    }

    public function store(StoreParametreRequest $request): JsonResponse
    {
        $param = Parametre::create($request->validated());
        PublicCache::forgetParametres();

        return response()->json([
            'message'    => 'Paramètre créé avec succès.',
            'parametre'  => new ParametreResource($param),
        ], 201);
    }

    public function show(Parametre $parametre)
    {
        return new ParametreResource($parametre);
    }

    public function update(UpdateParametreRequest $request, Parametre $parametre): JsonResponse
    {
        $parametre->update($request->validated());
        PublicCache::forgetParametres();

        return response()->json([
            'message'    => 'Paramètre mis à jour.',
            'parametre'  => new ParametreResource($parametre->fresh()),
        ]);
    }

    public function destroy(Parametre $parametre): JsonResponse
    {
        $parametre->delete();
        PublicCache::forgetParametres();

        return response()->json(['message' => 'Paramètre supprimé.']);
    }

    public function parGroupe(string $groupe)
    {
        return ParametreResource::collection(Parametre::where('groupe', $groupe)->get());
    }
}