<?php

namespace App\Http\Requests\Client\Common;

/**
 * 分页查询请求基类（Client 端）。
 *
 * 提供统一的 page/page_size 校验，避免每个控制器重复定义。
 */
class PaginationRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
