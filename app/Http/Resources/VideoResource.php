<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Générer l'URL vidéo complète pour les uploads
        $urlVideo = $this->url_video;
        if ($this->type_video === 'upload' && $this->url_video) {
            // Les vidéos uploadées sont stockées dans public/videos/, donc asset() suffit
            $urlVideo = asset($this->url_video);
        }

        return [
            'id'               => $this->id,
            'titre'            => $this->titre,
            'slug'             => $this->slug,
            'description'      => $this->description,
            'miniature'        => $this->miniature_url,
            'url_video'        => $urlVideo,
            'embed_url'        => $this->embed_url,
            'type_video'       => $this->type_video,
            'duree'            => $this->duree,
            'statut'           => $this->statut,
            'nombre_vues'      => $this->nombre_vues,
            'date_publication' => $this->date_publication,
            'categorie'        => new CategorieResource($this->whenLoaded('categorie')),
            'auteur'           => new UserResource($this->whenLoaded('user')),
            'created_at'       => $this->created_at,
        ];
    }
}