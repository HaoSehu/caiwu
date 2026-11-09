<?php

namespace App\Http\Requests\Client\V2\Invoice;

use App\Constants\PaymentGatewayCode;
use App\Http\Requests\Client\V2\Common\ClientFormRequest;
use Illuminate\Validation\Rule;

class PayByBalanceAndAlipayRequest extends ClientFormRequest
{
    public function rules(): array
    {
        return [
            'payment_session_token' => ['required', 'string', 'min:20', 'max:120'],
            // decimal:0,2 与后端 number_format(…,2) 分位一致，避免 10.999 被静默舍入成 11.00
            'balance_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            // 混付的第三方渠道由用户所选支付方式透传；缺省回落 alipay 兼容旧客户端
            'gateway' => ['nullable', 'string', Rule::in(PaymentGatewayCode::thirdPartyGateways())],
            'payment_type' => ['nullable', 'string', Rule::in(['alipay', 'wxpay'])],
        ];
    }
}
