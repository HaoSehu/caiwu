<?php

declare(strict_types=1);

namespace App\Http\Requests\Client\V2\Payment;

use App\Constants\PaymentGatewayCode;
use App\Http\Requests\Client\V2\Common\ClientFormRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use Illuminate\Validation\Rule;

class ListPaymentsRequest extends ClientFormRequest
{
    use HasDateRangeFilter;

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::in(PaymentGatewayCode::thirdPartyGateways())],
            'gateway' => ['nullable', 'string', Rule::in(PaymentGatewayCode::thirdPartyGateways())],
            'keyword' => ['nullable', 'string', 'max:80'],
            ...$this->dateRangeRules(),
            ...$this->paginationRules(),
            'pageSize' => ['prohibited'],
            'per_page' => ['prohibited'],
        ];
    }
}
