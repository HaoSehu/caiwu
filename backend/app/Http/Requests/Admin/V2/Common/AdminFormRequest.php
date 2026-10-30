<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Common;

use App\Http\Requests\Admin\V2\Concerns\RejectsLegacyPagination;
use App\Support\AdminPrivacy;
use App\Support\Pagination;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class AdminFormRequest extends FormRequest
{
    use RejectsLegacyPagination;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    protected function paginationRules(int $maxPageSize = Pagination::MAX_PAGE_SIZE): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:'.$maxPageSize],
        ];
    }

    public function perPage(int $default = 20, int $max = Pagination::MAX_PAGE_SIZE): int
    {
        $validated = $this->validated();
        $pageSize = (int) ($validated['page_size'] ?? $default);

        return Pagination::clamp($pageSize, $max);
    }

    /**
     * @param  array<string, string>  $sensitiveFields
     * @param  array<string, string>  $keywordFields
     */
    protected function rejectPrivacyFilters(Validator $validator, array $sensitiveFields = [], array $keywordFields = []): void
    {
        foreach (AdminPrivacy::forbiddenFilterMessages($this->all(), $sensitiveFields, $keywordFields, $this->user()) as $field => $message) {
            $validator->errors()->add($field, $message);
        }
    }
}
