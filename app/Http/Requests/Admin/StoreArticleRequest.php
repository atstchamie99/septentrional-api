<?php
// app/Http/Requests/Admin/StoreArticleRequest.php

namespace App\Http\Requests\Admin;

use App\Models\Article;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Article::class);
    }

    public function rules(): array
    {
        return [
            'titre'             => 'required|string|max:255',
            'redacteur_nom'     => 'nullable|string|max:255',
            'slug'              => 'nullable|string|max:255|unique:articles,slug',
            'extrait'           => 'required|string',
            'contenu'           => 'required|string',
            'categorie_id'      => 'required|exists:categories,id',
            'categorie_ids'     => 'sometimes|array|max:50',
            'categorie_ids.*'   => 'integer|distinct|exists:categories,id',
            'etiquette_ids'     => 'nullable|array',
            'etiquette_ids.*'   => 'exists:etiquettes,id',
            'image_principale'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:51200',
            'image_media_id'       => 'nullable|integer|exists:media_library,id',
            'image_principale_url' => 'nullable|string|max:500',
            'statut'            => ['required', Rule::in(['brouillon', 'publie', 'archive'])],
            'est_alerte'        => 'boolean',
            'meta_titre'        => 'nullable|string|max:255',
            'meta_description'  => 'nullable|string|max:500',
            'date_publication'  => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'meta_titre.max' => 'Le meta titre ne doit pas dépasser 255 caractères.',
            'meta_description.max' => 'La meta description ne doit pas dépasser 500 caractères.',
        ];
    }
}
