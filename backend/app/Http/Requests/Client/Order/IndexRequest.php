<?php

namespace App\Http\Requests\Client\Order;

use App\Http\Requests\Client\Common\ClientFormRequest;

class IndexRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', 'in:new,renew,addon'],
            'keyword' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
