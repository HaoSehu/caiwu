<?php

namespace App\Http\Requests\Client\Recharge;

use App\Http\Requests\Client\Common\ClientFormRequest;

class StatusRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'poll_token' => ['required', 'string', 'min:20', 'max:120'],
        ];
    }
}
