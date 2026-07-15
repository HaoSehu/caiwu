<?php

namespace App\Http\Requests\Client\Blackhole;

use App\Http\Requests\Client\Common\ClientFormRequest;

class QueryRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'ip' => 'required|ip',
        ];
    }
}
