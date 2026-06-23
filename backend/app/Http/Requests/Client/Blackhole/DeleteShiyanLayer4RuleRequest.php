<?php

namespace App\Http\Requests\Client\Blackhole;

use App\Http\Requests\Client\Common\ClientFormRequest;

class DeleteShiyanLayer4RuleRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'ip' => 'required|ip',
            'rule_id' => 'required|string|max:64',
        ];
    }
}
