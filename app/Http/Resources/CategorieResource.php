<?php
// app/Http/Resources/CategorieResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategorieResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'nom'         => $this->nom,
            'slug'        => $this->slug,
            'description' => $this->description,
            'couleur'     => $this->couleur,
            'icone'       => $this->icone,
            'ordre'       => $this->ordre,
            'parent_id'   => $this->parent_id,
            'enfants'     => CategorieResource::collection($this->whenLoaded('enfants')),
            'created_at'  => $this->created_at,
        ];
    }
}