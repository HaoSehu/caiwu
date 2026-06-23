<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class QuoteTrafficPackageRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'target_value' => ['required', 'integer', 'min:1'],
        ];
    }
}
