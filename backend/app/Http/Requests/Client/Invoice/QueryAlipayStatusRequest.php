<?php

namespace App\Http\Requests\Client\Invoice;

use App\Http\Requests\Client\Common\ClientFormRequest;

class QueryAlipayStatusRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'payment_no' => ['required', 'string', 'max:50'],
            'poll_token' => ['required', 'string', 'min:20', 'max:120'],
        ];
    }
}
