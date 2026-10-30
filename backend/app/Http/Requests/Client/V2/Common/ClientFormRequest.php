<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\V2\Common;

use App\Support\Pagination;
use Illuminate\Foundation\Http\FormRequest;

abstract class ClientFormRequest extends FormRequest
{
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

    /**
     * 页大小提取。
     *
     * 上限默认取全局 MAX_PAGE_SIZE：过去这里硬钳 50，与校验规则的 100、服务层的 100
     * 形成三套口径，同一个 page_size 在 51–100 区间会因调用路径不同而返回不同结果。
     * 默认值仍按端给（Client 15），个别列表需要更小上限时显式传 $max。
     */
    public function perPage(int $default = 15, int $max = Pagination::MAX_PAGE_SIZE): int
    {
        $validated = $this->validated();
        $pageSize = (int) ($validated['page_size'] ?? $default);

        return Pagination::clamp($pageSize, $max);
    }
}
