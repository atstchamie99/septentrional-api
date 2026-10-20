<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbonneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom'   => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:abonnes,email',
        ];
    }
}
