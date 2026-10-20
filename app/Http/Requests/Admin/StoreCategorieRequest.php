<?php
// app/Http/Requests/Admin/StoreCategorieRequest.php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategorieRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->estAdmin();
    }

    public function rules(): array
    {
        return [
            'nom'         => 'required|string|max:255',
            'slug'        => 'nullable|string|max:255|unique:categories,slug',
            'description' => 'nullable|string',
            'couleur'     => 'nullable|string|max:7',
            'icone'       => 'nullable|string|max:255',
            'ordre'       => 'integer|min:0',
            'parent_id'   => 'nullable|exists:categories,id',
        ];
    }
}