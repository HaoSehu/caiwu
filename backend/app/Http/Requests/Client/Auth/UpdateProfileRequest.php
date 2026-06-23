<?php

namespace App\Http\Requests\Client\Auth;

use App\Http\Requests\Client\Common\ClientFormRequest;

class UpdateProfileRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'nickname' => 'nullable|string|max:50',
        ];
    }
}
