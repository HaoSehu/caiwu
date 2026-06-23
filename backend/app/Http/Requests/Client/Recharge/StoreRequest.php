<?php

namespace App\Http\Requests\Client\Recharge;

use App\Http\Requests\Client\Common\ClientFormRequest;

class StoreRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
        ];
    }
}
