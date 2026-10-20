<?php

namespace App\Http\Resources\Public;

use App\Http\Resources\CategorieResource;
use App\Http\Resources\EtiquetteResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'titre'             => $this->titre,
            'redacteur_nom'     => $this->redacteur_nom,
            'slug'              => $this->slug,
            'extrait'           => $this->extrait,
            'contenu'           => $this->when($request->routeIs('v2.articles.show'), $this->contenu),
            'image_principale'  => $this->image_url,
            'est_alerte'        => $this->est_alerte,
            'nombre_vues'       => $this->nombre_vues,
            'meta_titre'        => $this->meta_titre,
            'meta_description'  => $this->meta_description,
            'date_publication'  => $this->date_publication,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'categorie'         => new CategorieResource($this->whenLoaded('categorie')),
            'categories'        => CategorieResource::collection($this->whenLoaded('categories')),
            'auteur'            => new UserResource($this->whenLoaded('user')),
            'etiquettes'        => EtiquetteResource::collection($this->whenLoaded('etiquettes')),
        ];
    }
}
