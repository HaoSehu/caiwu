<?php

declare(strict_types=1);

namespace App\Services\Integrations\Payments\Drivers;

use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Constants\PaymentGatewayCode;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\Data\PaymentRefundRequest;
use App\Services\Integrations\Payments\Data\PaymentRefundResult;
use App\Services\PaymentGateway\AlipayFaceToFaceService;

final readonly class AlipayFaceToFaceGateway implements PaymentGatewayInterface
{
    public function __construct(
        private AlipayFaceToFaceService $alipay,
    ) {}

    public function key(): string
    {
        return PaymentGatewayCode::ALIPAY_F2F_PLUGIN;
    }

    public function name(): string
    {
        return '支付宝当面付';
    }

    public function isEnabled(): bool
    {
        return $this->alipay->isEnabled();
    }

    public function matchesMerchantId(?string $merchantId): bool
    {
        return $this->alipay->matchesAppId($merchantId);
    }

    public function precreate(PaymentPrecreateRequest $request): PaymentPrecreateResult
    {
        $result = $this->alipay->precreate(
            $request->outTradeNo,
            $request->amount,
            $request->subject,
            $request->timeoutExpress,
        );

        return new PaymentPrecreateResult(
            qrCode: (string) ($result['qr_code'] ?? ''),
            outTradeNo: (string) ($result['out_trade_no'] ?? $request->outTradeNo),
            raw: $result,
        );
    }

    public function query(string $outTradeNo): PaymentQueryResult
    {
        $result = $this->alipay->query($outTradeNo);

        return new PaymentQueryResult(
            tradeStatus: (string) ($result['trade_status'] ?? ''),
            tradeNo: (string) ($result['trade_no'] ?? ''),
            outTradeNo: (string) ($result['out_trade_no'] ?? $outTradeNo),
            totalAmount: (string) ($result['total_amount'] ?? '0.00'),
            raw: (array) ($result['raw'] ?? $result),
        );
    }

    public function refund(PaymentRefundRequest $request): PaymentRefundResult
    {
        $result = $this->alipay->refund(
            outTradeNo: $request->outTradeNo,
            refundAmount: $request->refundAmount,
            refundReason: $request->refundReason,
            tradeNo: $request->tradeNo,
            outRequestNo: $request->outRequestNo,
        );

        return new PaymentRefundResult(
            tradeNo: (string) ($result['trade_no'] ?? ''),
            outTradeNo: (string) ($result['out_trade_no'] ?? $request->outTradeNo),
            refundFee: (string) ($result['refund_fee'] ?? number_format($request->refundAmount, 2, '.', '')),
            fundChange: (string) ($result['fund_change'] ?? ''),
            gmtRefundPay: (string) ($result['gmt_refund_pay'] ?? ''),
            raw: (array) ($result['raw'] ?? $result),
        );
    }

    public function verifyNotify(array $payload): bool
    {
        return $this->alipay->verifyNotify($payload);
    }
}
