<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'nom'          => $this->user?->name ?? $this->nom ?? 'Anonyme',
            'email'        => $this->when($request->user()?->estAdmin(), $this->email),
            'contenu'      => $this->contenu,
            'statut'       => $this->statut,
            'adresse_ip'   => $this->when($request->user()?->estAdmin(), $this->adresse_ip),
            'created_at'   => $this->created_at?->diffForHumans(),
            'reponses'     => CommentaireResource::collection($this->whenLoaded('reponses')),
            'moderateur'   => $this->whenLoaded('moderateur'),
        ];
    }
}