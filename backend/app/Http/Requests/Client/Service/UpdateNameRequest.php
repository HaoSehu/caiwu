<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class UpdateNameRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
