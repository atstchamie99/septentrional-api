<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCommentaireRequest;
use App\Http\Resources\CommentaireResource;
use App\Models\Article;
use App\Models\Commentaire;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CommentaireController extends Controller
{
    public function store(StoreCommentaireRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['adresse_ip'] = $request->ip();

        if ($request->user()) {
            $data['user_id'] = $request->user()->id;
            $data['nom'] = $request->user()->name;
        }

        $commentaire = Commentaire::create($data);

        return ApiResponse::created(
            new CommentaireResource($commentaire),
            'Commentaire publié avec succès.'
        );
    }

    public function parArticle(string $articleSlug)
    {
        $article = Article::publies()->where('slug', $articleSlug)->firstOrFail();

        $commentaires = $article->commentaires()
            ->nonRejetes()
            ->whereNull('parent_id')
            ->with(['reponses' => fn ($q) => $q->nonRejetes()])
            ->latest()
            ->paginate(20);

        return CommentaireResource::collection($commentaires);
    }
}
