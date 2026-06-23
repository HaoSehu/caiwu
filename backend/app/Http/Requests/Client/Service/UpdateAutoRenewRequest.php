<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class UpdateAutoRenewRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'auto_renew' => ['required', 'in:0,1'],
        ];
    }
}
