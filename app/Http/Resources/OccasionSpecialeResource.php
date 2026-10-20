<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OccasionSpecialeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'titre'      => $this->titre,
            'texte'      => $this->texte,
            'image'      => $this->image_url,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin'   => $this->date_fin?->toDateString(),
            'est_actif'  => $this->est_actif,
        ];
    }
}
