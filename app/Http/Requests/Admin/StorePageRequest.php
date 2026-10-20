<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->estAdmin() || $this->user()->estRedacteur();
    }

    public function rules(): array
    {
        return [
            'titre'    => 'required|string|max:255',
            'slug'     => 'nullable|string|max:255|unique:pages,slug',
            'contenu'  => 'required|string',
            'statut'   => 'required|in:brouillon,publie',
        ];
    }
}