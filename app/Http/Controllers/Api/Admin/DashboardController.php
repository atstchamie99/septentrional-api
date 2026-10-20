<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Abonne;
use App\Models\Article;
use App\Models\Commentaire;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $isAdmin = $request->user()->role === 'admin';

        return response()->json([
            'articles'   => (int) Article::count(),
            'videos'     => (int) Video::count(),
            'commentaires_en_attente' => (int) Commentaire::enAttente()->count(),
            'abonnes'    => $isAdmin ? (int) Abonne::actifs()->count() : 0,
            'articles_vues' => (int) Article::sum('nombre_vues'),
        ]);
    }
}
