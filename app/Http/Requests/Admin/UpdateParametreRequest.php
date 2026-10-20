<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParametreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->estAdmin();
    }

    public function rules(): array
    {
        $paramId = $this->route('parametre')->id;

        return [
            'cle'     => ['sometimes', 'string', 'max:255', Rule::unique('parametres')->ignore($paramId)],
            'valeur'  => 'sometimes|string',
            'type'    => 'sometimes|in:string,boolean,json,fichier',
            'groupe'  => 'sometimes|string|max:255',
        ];
    }
}