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
     * 标准分页参数规则。
     *
     * @param  string  $presence  分页参数的「存在性」规则。nullable 与 sometimes 对
     *                            这两个可选字段校验结果等价，两者并存只是历史写法差异；
     *                            统一走本方法，避免每个列表请求各写一遍。
     * @return array<string, list<string>>
     */
    protected function paginationRules(int $maxPageSize = Pagination::MAX_PAGE_SIZE, string $presence = 'nullable'): array
    {
        return [
            'page' => [$presence, 'integer', 'min:1'],
            'page_size' => [$presence, 'integer', 'min:1', 'max:'.$maxPageSize],
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
