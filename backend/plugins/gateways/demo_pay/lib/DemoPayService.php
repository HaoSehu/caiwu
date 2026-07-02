<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Gateways\DemoPay\Lib;

// PaymentGatewayInterface contract is handled by PluginPaymentGateway adapter
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\Data\PaymentRefundRequest;
use App\Services\Integrations\Payments\Data\PaymentRefundResult;
use Illuminate\Http\Response;

class DemoPayService
{
    public function key(): string
    {
        return 'demo_pay';
    }

    public function name(): string
    {
        return 'Demo 支付网关';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function isEnabled(array $config = []): bool
    {
        return filter_var($config['enabled'] ?? true, FILTER_VALIDATE_BOOL);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function matchesMerchantId(?string $merchantId, array $config = []): bool
    {
        $configuredMerchantId = trim((string) ($config['merchant_id'] ?? 'demo_merchant'));

        return $merchantId === null || $configuredMerchantId === '' || hash_equals($configuredMerchantId, $merchantId);
    }

    public function precreate(PaymentPrecreateRequest $request): PaymentPrecreateResult
    {
        return new PaymentPrecreateResult(
            qrCode: 'https://example.test/pay/demo?out_trade_no='.rawurlencode($request->outTradeNo),
            outTradeNo: $request->outTradeNo,
            raw: [
                'demo' => true,
                'amount' => $request->amount,
                'subject' => $request->subject,
            ],
        );
    }

    public function query(string $outTradeNo): PaymentQueryResult
    {
        return new PaymentQueryResult(
            tradeStatus: 'WAIT_BUYER_PAY',
            tradeNo: 'DEMO'.date('YmdHis'),
            outTradeNo: $outTradeNo,
            totalAmount: '0.00',
            raw: ['demo' => true],
        );
    }

    public function refund(PaymentRefundRequest $request): PaymentRefundResult
    {
        return new PaymentRefundResult(
            tradeNo: $request->tradeNo ?? 'DEMO'.date('YmdHis'),
            outTradeNo: $request->outTradeNo,
            refundFee: number_format($request->refundAmount, 2, '.', ''),
            fundChange: 'N',
            raw: ['demo' => true],
        );
    }

    public function verifyNotify(array $payload): bool
    {
        return ($payload['demo_sign'] ?? '') === 'ok';
    }

    public function buildNotifyResponse(bool $success): Response
    {
        return response($success ? 'success' : 'fail', 200)
            ->header('Content-Type', 'text/plain');
    }

    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
        $config = is_array($request['config'] ?? null) ? $request['config'] : [];

        return match ($action) {
            'payment.is_enabled' => $this->success($action, [
                'enabled' => $this->isEnabled($config),
            ]),
            'payment.matches_merchant' => $this->success($action, [
                'matched' => $this->matchesMerchantId(
                    array_key_exists('merchant_id', $payload) && $payload['merchant_id'] !== null
                        ? (string) $payload['merchant_id']
                        : null,
                    $config,
                ),
            ]),
            'payment.precreate' => $this->success($action, $this->precreate(new PaymentPrecreateRequest(
                outTradeNo: (string) ($payload['out_trade_no'] ?? ''),
                amount: (float) ($payload['amount'] ?? 0),
                subject: (string) ($payload['subject'] ?? ''),
                timeoutExpress: isset($payload['timeout_express']) ? (string) $payload['timeout_express'] : null,
            ))->toArray()),
            'payment.query' => $this->success($action, $this->query((string) ($payload['out_trade_no'] ?? ''))->toArray()),
            'payment.refund' => $this->success($action, $this->refund(new PaymentRefundRequest(
                outTradeNo: (string) ($payload['out_trade_no'] ?? ''),
                refundAmount: (float) ($payload['refund_amount'] ?? 0),
                refundReason: (string) ($payload['refund_reason'] ?? ''),
                tradeNo: isset($payload['trade_no']) ? (string) $payload['trade_no'] : null,
                outRequestNo: isset($payload['out_request_no']) ? (string) $payload['out_request_no'] : null,
            ))->toArray()),
            'payment.verify_notify' => $this->success($action, ['verified' => $this->verifyNotify($payload)]),
            default => ['success' => false, 'action' => $action, 'message' => 'Unsupported plugin action', 'data' => []],
        };
    }

    private function success(string $action, array $data): array
    {
        return ['success' => true, 'action' => $action, 'data' => $data];
    }
}
