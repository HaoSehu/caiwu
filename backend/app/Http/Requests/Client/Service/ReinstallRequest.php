<?php

namespace App\Http\Requests\Client\Service;

use App\Http\Requests\Client\Common\ClientFormRequest;

class ReinstallRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'os_id' => ['required', 'string', 'max:50'],
        ];
    }
}
