<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class ManualInvoiceEntryRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'paid_at' => ['required', 'date'],
            'payment_gateway' => ['required', 'string', 'in:manual,alipay,wechat,balance,bank_transfer,offline'],
            'trade_no' => ['nullable', 'string', 'max:100'],
            'send_email' => ['nullable', 'boolean'],
            'sync_business_flow' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only([
            'amount',
            'paid_at',
            'payment_gateway',
            'trade_no',
            'send_email',
            'sync_business_flow',
            'remark',
        ]);
    }
}
