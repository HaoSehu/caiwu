<?php

namespace App\Http\Requests\Admin\Log;

use App\Http\Requests\Admin\Common\AdminFormRequest;

abstract class LogListRequest extends AdminFormRequest
{
    protected function paginationRules(int $maxPageSize = 50): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.$maxPageSize],
        ];
    }

    public function perPage(int $default = 15, int $max = 50): int
    {
        $validated = $this->validated();
        $pageSize = (int) ($validated['per_page'] ?? $default);

        return max(1, min($pageSize, $max));
    }

    public function pageNumber(int $default = 1): int
    {
        $validated = $this->validated();
        $page = (int) ($validated['page'] ?? $default);

        return max(1, $page);
    }
}
