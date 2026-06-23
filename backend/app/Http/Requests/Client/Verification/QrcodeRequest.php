<?php

namespace App\Http\Requests\Client\Verification;

use App\Http\Requests\Client\Common\ClientFormRequest;

class QrcodeRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return ['certify_id' => 'required|string'];
    }
}
