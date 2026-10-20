<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModererCommentaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->estAdmin() || $this->user()->estRedacteur();
    }

    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::in(['approuve', 'rejete'])],
        ];
    }
}