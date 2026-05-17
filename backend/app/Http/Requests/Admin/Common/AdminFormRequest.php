<?php

namespace App\Http\Requests\Admin\Common;

use Illuminate\Foundation\Http\FormRequest;

abstract class AdminFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function paginationRules(int $maxPageSize = 100): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:'.$maxPageSize],
        ];
    }

    public function perPage(int $default = 20, int $max = 100): int
    {
        $validated = $this->validated();
        $pageSize = (int) ($validated['page_size'] ?? $default);

        return max(1, min($pageSize, $max));
    }
}
