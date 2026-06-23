<?php

namespace App\Http\Requests\Client\Payment;

use App\Constants\PaymentGatewayCode;
use App\Http\Requests\Client\Common\ClientFormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'integer'],
            'gateway' => ['nullable', 'string', Rule::in(PaymentGatewayCode::thirdPartyGateways())],
            'keyword' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
