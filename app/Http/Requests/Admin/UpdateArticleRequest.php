<?php
// app/Http/Requests/Admin/UpdateArticleRequest.php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('article'));
    }

    public function rules(): array
    {
        $articleId = $this->route('article')->id;

        return [
            'titre'             => 'sometimes|string|max:255',
            'redacteur_nom'     => 'sometimes|nullable|string|max:255',
            'slug'              => ['sometimes', 'string', 'max:255', Rule::unique('articles')->ignore($articleId)],
            // Les articles historiques peuvent avoir un extrait plus long que la limite de création actuelle.
            'extrait'           => 'sometimes|nullable|string',
            'contenu'           => 'sometimes|string',
            'categorie_id'      => 'sometimes|exists:categories,id',
            'categorie_ids'     => 'sometimes|array|max:50',
            'categorie_ids.*'   => 'integer|distinct|exists:categories,id',
            'etiquette_ids'     => 'nullable|array',
            'etiquette_ids.*'   => 'exists:etiquettes,id',
            'image_principale'     => 'nullable|image|mimes:jpeg,png,jpg,webp|max:51200',
            'image_media_id'       => 'nullable|integer|exists:media_library,id',
            'image_principale_url' => 'nullable|string|max:500',
            'remove_image'     => 'nullable|boolean',
            'statut'            => ['sometimes', Rule::in(['brouillon', 'publie', 'archive'])],
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
