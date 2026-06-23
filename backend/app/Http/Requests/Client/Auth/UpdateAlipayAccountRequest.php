<?php

namespace App\Http\Requests\Client\Auth;

use App\Http\Requests\Client\Common\ClientFormRequest;

class UpdateAlipayAccountRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'real_name' => ['required', 'string', 'max:80'],
            'account' => ['required', 'regex:/^1[3-9]\d{9}$/'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
