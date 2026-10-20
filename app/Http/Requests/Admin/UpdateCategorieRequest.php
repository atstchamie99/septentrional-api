<?php
// app/Http/Requests/Admin/UpdateCategorieRequest.php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategorieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->estAdmin();
    }

    public function rules(): array
    {
        $categorie = $this->route('categorie');

        return [
            'nom'         => 'sometimes|required|string|max:255',
            'slug'        => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($categorie?->id),
            ],
            'description' => 'nullable|string',
            'couleur'     => 'nullable|string|max:7',
            'icone'       => 'nullable|string|max:255',
            'ordre'       => 'integer|min:0',
            'parent_id'   => 'nullable|exists:categories,id',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $categorie = $this->route('categorie');
            $parentId = $this->input('parent_id');

            if (!$categorie || !$parentId) {
                return;
            }

            if ((int) $parentId === $categorie->id) {
                $validator->errors()->add('parent_id', 'Une catégorie ne peut pas être son propre parent.');

                return;
            }

            if (in_array((int) $parentId, $this->descendantIds($categorie), true)) {
                $validator->errors()->add('parent_id', 'Une catégorie ne peut pas avoir l\'une de ses descendantes comme parent.');
            }
        });
    }

    /**
     * Récupère récursivement les identifiants de toutes les descendantes de la catégorie.
     */
    protected function descendantIds($categorie): array
    {
        $ids = [];

        foreach ($categorie->enfants()->get() as $enfant) {
            $ids[] = $enfant->id;
            $ids = array_merge($ids, $this->descendantIds($enfant));
        }

        return $ids;
    }
}
