<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListRequest extends FormRequest
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
            'q' => 'nullable|string|max:255',
            'categorie' => 'nullable|string|max:255',
            'etiquette' => 'nullable|string|max:255',
            'sort' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'mois' => 'nullable|integer|min:1|max:12',
            'annee' => 'nullable|integer|min:2000|max:2100',
            'periode' => 'nullable|string|max:10',
        ];
    }

    /**
     * Get the validated sort column, ensuring it's in the allowed list.
     */
    public function getSortColumn(array $allowed, string $default = 'created_at'): string
    {
        $sort = $this->validated('sort', '');
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
        $sort = $this->validated('sort', '');

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
        return $this->validated('q');
    }

    /**
     * Get the validated per_page value.
     */
    public function getPerPage(int $default = 12, int $max = 100): int
    {
        $perPage = $this->validated('per_page', $default);

        if (!is_int($perPage)) {
            $perPage = (int) $perPage;
        }

        return max(1, min($perPage, $max));
    }
}
