<?php

namespace App\Http\Requests\Client\Ticket;

use App\Http\Requests\Client\Common\ClientFormRequest;

class ServiceOptionsRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
