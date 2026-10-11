<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Order;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

/**
 * 订单退款标记（纯标记，不动资金）请求：与账单侧同规则——金额必填、原因必填，
 * 服务层解析关联账单后走同一守卫链（订单↔账单联动）。
 */
class MarkOrderRefundRequest extends AdminFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
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
