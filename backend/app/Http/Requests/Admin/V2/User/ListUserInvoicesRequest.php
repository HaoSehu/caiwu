<?php

namespace App\Http\Requests\Admin\V2\User;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class ListUserInvoicesRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            // 账单状态域已收敛为 0 待支付/1 已支付/4 已取消/5 已退款，历史值 2/3 已迁移，不再放行
            'status' => ['nullable', 'in:0,1,5'],
            'type' => ['nullable', 'in:normal,renew,manual,upgrade'],
        ], $this->legacyPaginationRules());
    }

    public function filters(): array
    {
        return $this->safe()->only([
            'status',
            'type',
        ]);
    }
}
