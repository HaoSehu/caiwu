<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class ModuleStatusRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:host,reinstall,repassword'],
        ];
    }
}
