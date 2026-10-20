<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => 'Le terme de recherche ne peut pas dépasser 255 caractères.',
            'sort.max' => 'Le champ de tri ne peut pas dépasser 255 caractères.',
            'per_page.min' => 'Le nombre d\'éléments par page doit être au moins 1.',
            'per_page.max' => 'Le nombre d\'éléments par page ne peut pas dépasser 100.',
            'page.min' => 'Le numéro de page doit être au moins 1.',
        ];
    }

    /**
     * Get the validated sort column, ensuring it's in the allowed list.
     */
    public function getSortColumn(array $allowed, string $default = 'created_at'): string
    {
        $sort = $this->validated('sort') ?? '';
        $column = ltrim($sort, '-');

        if (empty($column) || !in_array($column, $allowed, true)) {
            return $default;
        }

        return $column;
    }

    /**
     * Get the sort direction from the validated sort parameter.
     */
    public function getSortDirection(string $default = 'desc'): string
    {
        $sort = $this->validated('sort') ?? '';

        if (empty($sort)) {
            return $default;
        }

        return str_starts_with($sort, '-') ? 'desc' : 'asc';
    }

    /**
     * Get the validated search term.
     */
    public function getSearchTerm(): ?string
    {
        return $this->validated('q') ?? null;
    }

    /**
     * Get the validated per_page value.
     */
    public function getPerPage(int $default = 12, int $max = 100): int
    {
        $perPage = $this->validated('per_page') ?? $default;

        return max(1, min((int) $perPage, $max));
    }

    /**
     * Get the validated page value.
     */
    public function getPage(int $default = 1): int
    {
        $page = $this->validated('page') ?? $default;

        return max(1, (int) $page);
    }
}
