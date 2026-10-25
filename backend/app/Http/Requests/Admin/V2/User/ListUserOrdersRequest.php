<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class ListUserOrdersRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'status' => ['nullable', 'in:0,1,4,5'],
            'type' => ['nullable', 'in:new,renew,upgrade'],
            // 服务控制台「相关订单」按实例过滤；用户详情订单页不传该参数，行为不变。
            'service_id' => ['nullable', 'integer', 'min:1'],
        ], $this->legacyPaginationRules());
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return $this->safe()->only([
            'status',
            'type',
            'service_id',
        ]);
    }
}
