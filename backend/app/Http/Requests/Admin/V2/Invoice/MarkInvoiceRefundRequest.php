<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Invoice;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

/**
 * 账单退款标记（纯标记，不动资金）请求：金额必填（消除「标记了但不知退多少」
 * 的歧义），原因必填（审计与追溯），服务层再做可退余额/状态/类型守卫。
 */
class MarkInvoiceRefundRequest extends AdminFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // 标记金额必须显式给出：正数、最多两位小数、不超过剩余可退额
            // （服务层按 refunds 累计与真退款同源口径二次校验，防绕过前端）。
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/'],
            'remark' => ['required', 'string', 'min:2', 'max:200'],
            ...$this->allPaginationRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => '请输入退款标记金额',
            'amount.regex' => '金额最多保留两位小数',
            'remark.required' => '请填写退款标记原因',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->only([
            'amount',
            'remark',
        ]);
    }
}
