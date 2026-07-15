<?php

namespace App\Http\Requests\Client\Blackhole;

use App\Http\Requests\Client\Common\ClientFormRequest;

class SetShiyanLayer7RuleRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'ip' => 'required|ip',
            'rule_id' => 'required|integer|min:1',
            'enabled' => 'required|boolean',
        ];
    }
}
