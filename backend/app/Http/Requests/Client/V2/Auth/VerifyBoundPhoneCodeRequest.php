<?php

namespace App\Http\Requests\Client\V2\Auth;

use App\Http\Requests\Client\V2\Common\ClientFormRequest;

class VerifyBoundPhoneCodeRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'code' => 'required|string|size:6',
        ];
    }
}
