<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class CreateRenewOrderRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'billing_cycle' => ['required', 'string', 'max:30'],
            'user_coupon_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
