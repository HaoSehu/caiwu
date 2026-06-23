<?php

namespace App\Http\Requests\Client\Invoice;

use App\Http\Requests\Client\Common\ClientFormRequest;

class StoreRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'billing_cycle' => ['required', 'string', 'max:30'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'config' => ['nullable', 'array'],
            'quote_token' => ['required', 'string', 'min:20', 'max:120'],
            'user_coupon_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
