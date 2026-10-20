<?php

namespace App\Http\Resources\Public;

use App\Http\Resources\CategorieResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicVideoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $urlVideo = $this->url_video;
        if ($this->type_video === 'upload' && $this->url_video) {
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
            'nombre_vues'      => $this->nombre_vues,
            'date_publication' => $this->date_publication,
            'categorie'        => new CategorieResource($this->whenLoaded('categorie')),
            'auteur'           => new UserResource($this->whenLoaded('user')),
            'created_at'       => $this->created_at,
            'updated_at'       => $this->updated_at,
        ];
    }
}
