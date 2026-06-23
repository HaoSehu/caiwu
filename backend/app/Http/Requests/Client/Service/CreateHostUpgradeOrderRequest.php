<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class CreateHostUpgradeOrderRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'billing_cycle' => ['required', 'string', 'max:30'],
            'promo_code' => ['nullable', 'string', 'max:100'],
        ];
    }
}
