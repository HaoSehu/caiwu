<?php

declare(strict_types=1);

namespace App\Services\Integrations\Payments;

use App\Constants\PaymentGatewayCode;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;

final readonly class PaymentGatewayManager
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
    ) {}

    public function gateway(?string $key = null): PaymentGatewayInterface
    {
        $selectedKey = trim((string) ($key ?: config('integrations.payments.default', PaymentGatewayCode::ALIPAY_F2F_PLUGIN)));

        return $this->registry->get($selectedKey);
    }

    public function alipay(): PaymentGatewayInterface
    {
        return $this->gateway(PaymentGatewayCode::ALIPAY_F2F_PLUGIN);
    }
}
