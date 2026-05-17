<?php

namespace App\Http\Requests\Admin\User;

use App\Http\Requests\Admin\Common\AdminFormRequest;

class RefundUserInvoiceRequest extends AdminFormRequest
{
    public function rules(): array
    {
        return [
            'refund_method' => ['required', 'string', 'in:balance,original'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'remark' => ['required', 'string', 'min:2', 'max:200'],
        ];
    }

    public function payload(): array
    {
        return $this->safe()->only([
            'refund_method',
            'amount',
            'remark',
        ]);
    }
}
