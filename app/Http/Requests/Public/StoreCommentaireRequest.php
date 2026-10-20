<?php

namespace App\Http\Requests\Public;

use App\Models\Article;
use App\Models\Commentaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCommentaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom'              => 'required|string|max:255',
            'email'            => 'required|email|max:255',
            'contenu'          => 'required|string|max:2000',
            'parent_id'        => 'nullable|exists:commentaires,id',
            'commentable_type' => 'required|in:App\Models\Article',
            'commentable_id'   => 'required|integer',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nom'     => is_string($this->nom) ? trim(strip_tags($this->nom)) : $this->nom,
            'contenu' => is_string($this->contenu) ? trim(strip_tags($this->contenu)) : $this->contenu,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->input('commentable_type');
            $id = (int) $this->input('commentable_id');

            $isPublished = match ($type) {
                Article::class => Article::publies()->whereKey($id)->exists(),
                default        => false,
            };

            if (! $isPublished) {
                $validator->errors()->add('commentable_id', 'Contenu introuvable ou non publié.');
            }

            if ($this->filled('parent_id')) {
                $parent = Commentaire::find($this->input('parent_id'));

                if (! $parent
                    || $parent->commentable_type !== $type
                    || (int) $parent->commentable_id !== $id) {
                    $validator->errors()->add('parent_id', 'Commentaire parent invalide.');
                }
            }
        });
    }
}
