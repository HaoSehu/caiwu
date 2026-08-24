<?php

namespace App\Http\Requests\Client\V2\Recharge;

use App\Constants\PaymentGatewayCode;
use App\Http\Requests\Client\V2\Common\ClientFormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            // decimal:0,2 限制最多两位小数，与后端 number_format(…,2) 分位规范化一致
            'amount' => ['required', 'numeric', 'min:1', 'max:50000', 'decimal:0,2'],
            'gateway' => ['nullable', 'string', Rule::in(PaymentGatewayCode::thirdPartyGateways())],
            'payment_type' => ['nullable', 'string', Rule::in(['alipay', 'wxpay'])],
        ];
    }
}
