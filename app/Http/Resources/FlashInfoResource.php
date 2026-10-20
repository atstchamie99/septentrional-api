<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlashInfoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'texte'      => $this->texte,
            'article_id' => $this->article_id,
            'article'    => $this->whenLoaded('article', function () {
                return [
                    'id'    => $this->article->id,
                    'slug'  => $this->article->slug,
                    'titre' => $this->article->titre,
                    'image_principale' => $this->article->image_principale,
                ];
            }),
            'est_actif'  => $this->est_actif,
            'date_debut' => $this->date_debut,
            'date_fin'   => $this->date_fin,
            'created_at' => $this->created_at,
        ];
    }
}