<?php

namespace App\Http\Requests\Client\Notification;

use App\Http\Requests\Client\Common\ClientFormRequest;

class IndexRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'unread_only' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
