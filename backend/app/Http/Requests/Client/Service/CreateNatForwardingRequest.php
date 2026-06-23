<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class CreateNatForwardingRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'ext_port' => ['nullable', 'integer', 'between:1,65535'],
            'int_port' => ['required', 'integer', 'between:1,65535'],
            'protocol' => ['required', 'string', 'in:1,2,3'],
        ];
    }
}
