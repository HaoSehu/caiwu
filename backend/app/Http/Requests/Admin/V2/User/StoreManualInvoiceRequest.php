<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\V2\User;

use App\Constants\ManualPaymentGateway;
use App\Http\Requests\Admin\V2\Common\AdminFormRequest;

class StoreManualInvoiceRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'trade_no' => ['nullable', 'string', 'max:100'],
            'payment_gateway' => ['required', 'string', 'in:'.ManualPaymentGateway::rule()],
            'remark' => ['required', 'string', 'min:2', 'max:200'],
            'per_page' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.regex' => '金额最多保留两位小数',
            'payment_gateway.in' => '请选择合法的支付方式',
            'remark.required' => '请填写补录备注',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->only([
            'amount',
            'paid_at',
            'trade_no',
            'payment_gateway',
            'remark',
        ]);
    }
}
