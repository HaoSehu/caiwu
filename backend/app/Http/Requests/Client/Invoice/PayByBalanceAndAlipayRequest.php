<?php

namespace App\Http\Requests\Client\Invoice;

use App\Http\Requests\Client\Common\ClientFormRequest;

class PayByBalanceAndAlipayRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'payment_session_token' => ['required', 'string', 'min:20', 'max:120'],
            'balance_amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
