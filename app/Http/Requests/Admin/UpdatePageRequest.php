<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->estAdmin() || $this->user()->estRedacteur();
    }

    public function rules(): array
    {
        $pageId = $this->route('page')->id;

        return [
            'titre'    => 'sometimes|string|max:255',
            'slug'     => ['sometimes', 'string', 'max:255', Rule::unique('pages')->ignore($pageId)],
            'contenu'  => 'sometimes|string',
            'statut'   => 'sometimes|in:brouillon,publie',
        ];
    }
}