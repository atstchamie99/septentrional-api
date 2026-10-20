<?php
// app/Http/Controllers/Api/Admin/CategorieController.php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategorieRequest;
use App\Http\Requests\Admin\UpdateCategorieRequest;
use App\Http\Resources\CategorieResource;
use App\Models\Categorie;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;

class CategorieController extends Controller
{
    public function index()
    {
        $categories = Categorie::with('enfants')
            ->orderBy('ordre')
            ->get();

        return CategorieResource::collection($categories);
    }

    public function store(StoreCategorieRequest $request): JsonResponse
    {
        $categorie = Categorie::create($request->validated());

        return response()->json([
            'message'    => 'Catégorie créée avec succès.',
            'categorie'  => new CategorieResource($categorie),
        ], 201);
    }

    public function show(Categorie $categorie)
    {
        return new CategorieResource($categorie->load('enfants', 'articles'));
    }

    public function update(UpdateCategorieRequest $request, Categorie $categorie): JsonResponse
    {
        $categorie->update($request->validated());

        return response()->json([
            'message'    => 'Catégorie mise à jour.',
            'categorie'  => new CategorieResource($categorie->fresh()),
        ]);
    }

    public function destroy(Categorie $categorie): JsonResponse
    {
        if ($categorie->enfants()->exists() || $categorie->articles()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer : cette catégorie a des enfants ou des articles.',
            ], 422);
        }

        AuditLog::record('Catégorie supprimée', ['categorie_id' => $categorie->id, 'nom' => $categorie->nom]);

        $categorie->delete();

        return response()->json(['message' => 'Catégorie supprimée.']);
    }
}