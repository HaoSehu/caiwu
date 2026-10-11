<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\Invoice;

use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

/**
 * 账单退款到余额（真退款，动资金）请求。
 *
 * 与 MarkInvoiceRefundRequest 同构校验，但语义完全不同：本请求会真实增加
 * 用户余额（AccountService）、写台账（FinanceLedgerWriter）、生成红字账单与
 * 充值记录（FinanceDocumentService）。权限复用 finance.refund（与纯标记同一
 * 入口两种方式），服务层再做状态/类型/可退额/通道互斥守卫。
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
        ]);
    }
}
