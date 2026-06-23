<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class CreateSecurityGroupRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
