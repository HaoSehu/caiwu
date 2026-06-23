<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class CreateSecurityRuleRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'direction' => ['required', 'string', 'max:20'],
            'protocol' => ['required', 'string', 'max:50'],
            'port' => ['required', 'string', 'max:100'],
            'ip' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
