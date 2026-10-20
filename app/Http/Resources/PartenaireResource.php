<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartenaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'nom'        => $this->nom,
            'logo'       => $this->logo_url,
            'lien'       => $this->lien,
            'position'   => $this->position,
            'date_debut' => $this->date_debut,
            'date_fin'   => $this->date_fin,
            'est_actif'  => $this->est_actif,
        ];
    }
}