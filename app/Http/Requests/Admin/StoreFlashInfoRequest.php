<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlashInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->estAdmin() || $this->user()->estRedacteur();
    }

    public function rules(): array
    {
        return [
            'texte'       => 'required|string|max:1000',
            'article_id'  => 'nullable|exists:articles,id',
            'est_actif'   => 'boolean',
            'date_debut'  => 'nullable|date',
            'date_fin'    => 'nullable|date|after_or_equal:date_debut',
        ];
    }
}