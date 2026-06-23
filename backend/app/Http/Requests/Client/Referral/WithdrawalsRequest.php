<?php

namespace App\Http\Requests\Client\Referral;

use App\Http\Requests\Client\Common\ClientFormRequest;

class WithdrawalsRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
