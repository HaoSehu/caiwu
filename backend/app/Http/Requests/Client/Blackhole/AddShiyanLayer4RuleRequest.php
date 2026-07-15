<?php

namespace App\Http\Requests\Client\Blackhole;

use App\Http\Requests\Client\Common\ClientFormRequest;

class AddShiyanLayer4RuleRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'ip' => 'required|ip',
            'mode' => 'required|integer|in:1,2',
        ];
    }
}
