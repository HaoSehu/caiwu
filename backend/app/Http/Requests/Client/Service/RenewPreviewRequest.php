<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class RenewPreviewRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'billing_cycle' => ['nullable', 'string', 'max:30'],
            'user_coupon_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
