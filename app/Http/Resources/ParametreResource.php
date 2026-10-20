<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParametreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'cle'    => $this->cle,
            'valeur' => $this->valeur_castee,
            'type'   => $this->type,
            'groupe' => $this->groupe,
        ];
    }
}