<?php

namespace App\Http\Requests\Admin\Order;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class UpdateOrderPaymentStatusRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:mark_paid,mark_unpaid,refund'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'paid_at' => ['nullable', 'date'],
            'payment_gateway' => ['nullable', 'string', 'in:manual,alipay,wechat,balance,bank_transfer,offline'],
            'refund_method' => ['nullable', 'string', 'in:balance,original'],
            'trade_no' => ['nullable', 'string', 'max:100'],
            'send_email' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string', 'max:200'],
            'sync_business_flow' => ['nullable', 'boolean'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only([
            'action',
            'amount',
            'paid_at',
            'payment_gateway',
            'refund_method',
            'trade_no',
            'send_email',
            'remark',
            'sync_business_flow',
        ]);
    }
}
