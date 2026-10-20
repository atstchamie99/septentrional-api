<?php
// app/Http/Controllers/Api/Public/CategorieController.php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategorieResource;
use App\Models\Categorie;

class CategorieController extends Controller
{
    public function index()
    {
        $categories = Categorie::withCount(['articles' => function ($q) {
                $q->publies();
            }])
            ->orderBy('ordre')
            ->get();

        return CategorieResource::collection($categories);
    }

    public function show(string $slug)
    {
        $categorie = Categorie::where('slug', $slug)
            ->with(['enfants', 'articles' => function ($q) {
                $q->publies()->latest()->limit(10);
            }])
            ->firstOrFail();

        return new CategorieResource($categorie);
    }
}