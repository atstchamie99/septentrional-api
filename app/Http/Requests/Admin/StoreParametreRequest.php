<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreParametreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->estAdmin();
    }

    public function rules(): array
    {
        return [
            'cle'     => 'required|string|max:255|unique:parametres,cle',
            'valeur'  => 'required|string',
            'type'    => 'required|in:string,boolean,json,fichier',
            'groupe'  => 'required|string|max:255',
        ];
    }
}