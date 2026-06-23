<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class UpdateRemarkRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'remark' => ['nullable', 'string', 'max:120'],
        ];
    }
}
