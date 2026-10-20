<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'titre'      => $this->titre,
            'slug'       => $this->slug,
            'contenu'    => $this->contenu,
            'meta_titre' => $this->meta_titre,
            'meta_description' => $this->meta_description,
            'date_publication' => $this->date_publication,
            'statut'     => $this->statut,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}