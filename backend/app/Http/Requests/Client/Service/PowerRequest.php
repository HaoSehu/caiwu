<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class PowerRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:on,off,reboot,hard_off,hard_reboot'],
        ];
    }
}
