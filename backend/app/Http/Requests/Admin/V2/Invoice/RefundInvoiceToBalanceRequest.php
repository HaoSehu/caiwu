<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Invoice;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

/**
 * 账单退款到余额（唯一退款通道，动资金）请求。
 *
 * 本请求会真实增加用户余额（FinanceAccountService）、写台账与批次（FinanceLedgerService）、
 * 生成红字账单与退款单（FinanceDocumentService）。权限复用 finance.refund，
 * 服务层再做状态/类型/可退额/交付守卫；idempotency_key 必填，同键重放直接返回既有结果。
 */
class RefundInvoiceToBalanceRequest extends AdminFormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // 金额必须显式给出：正数、最多两位小数；上限由服务层按原单可退额校验
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/'],
            'remark' => ['required', 'string', 'min:2', 'max:200'],
            // 幂等键：前端每次发起生成 UUID；网络重试携带同键时服务端直接返回既有结果
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[\w\-.:]+$/'],
            ...$this->allPaginationRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => '请输入退款金额',
            'amount.regex' => '金额最多保留两位小数',
            'remark.required' => '请填写退款原因',
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
            'idempotency_key',
        ]);
    }
}
