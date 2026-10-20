<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModererCommentaireRequest;
use App\Http\Resources\CommentaireResource;
use App\Models\Article;
use App\Models\Commentaire;
use App\Models\Episode;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;

class CommentaireController extends Controller
{
    public function index()
    {
        return CommentaireResource::collection($this->commentaireQuery()->paginate(30));
    }

    public function enAttente()
    {
        return CommentaireResource::collection(
            $this->commentaireQuery()->enAttente()->paginate(30)
        );
    }

    public function moderer(ModererCommentaireRequest $request, Commentaire $commentaire): JsonResponse
    {
        $commentaire->update([
            'statut'     => $request->statut,
            'modere_par' => $request->user()->id,
        ]);

        return response()->json([
            'message'      => 'Commentaire ' . match($request->statut) {
                'rejete'   => 'rejeté',
                'approuve' => 'réapprouvé',
                default    => 'mis à jour',
            } . '.',
            'commentaire'  => new CommentaireResource($commentaire),
        ]);
    }

    public function destroy(Commentaire $commentaire): JsonResponse
    {
        $commentaire->reponses()->delete();
        $commentaire->delete();

        return response()->json(['message' => 'Commentaire supprimé.']);
    }

    private function commentaireQuery()
    {
        return Commentaire::with([
            'user',
            'commentable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Article::class => ['categorie'],
                    Episode::class => ['emission'],
                ]);
            },
        ])->latest();
    }
}
