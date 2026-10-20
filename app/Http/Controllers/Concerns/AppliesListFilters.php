<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

trait AppliesListFilters
{
    protected function applySearch(Builder $query, ?string $term, array $columns): void
    {
        if (! $term) {
            return;
        }

        $query->where(function (Builder $q) use ($term, $columns) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    protected function applySort(Builder $query, Request $request, array $allowed, string $defaultColumn = 'created_at', string $defaultDir = 'desc'): void
    {
        $validator = Validator::make($request->only('sort'), [
            'sort' => ['nullable', 'string', 'max:50', 'regex:/^-?[a-zA-Z_]+$/'],
        ]);

        $sort = $validator->valid()['sort'] ?? null;
        if ($sort === null || $sort === '') {
            $query->orderBy($defaultColumn, $defaultDir);
            return;
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (!in_array($column, $allowed, true)) {
            $query->orderBy($defaultColumn, $defaultDir);
            return;
        }

        $query->orderBy($column, $direction);
    }

    protected function perPage(Request $request, int $default = 12, int $max = 50): int
    {
        $validator = Validator::make($request->only('per_page'), [
            'per_page' => ['nullable', 'integer', 'min:1', "max:{$max}"],
        ]);

        return (int) ($validator->valid()['per_page'] ?? $default);
    }
}
