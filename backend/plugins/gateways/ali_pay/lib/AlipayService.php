<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Gateways\AliPay\Lib;

class AlipayService
{
    private ?AlipayClient $client = null;

    public function key(): string
    {
        return 'alipay';
    }

    public function name(): string
    {
        return '支付宝当面付';
    }

    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
        $config = is_array($request['config'] ?? null) ? $request['config'] : [];
        $client = $this->client($config);

        return match ($action) {
            'payment.is_enabled' => $this->success($action, [
                'enabled' => $client->isEnabled(),
            ]),
            'payment.matches_merchant' => $this->success($action, [
                'matched' => $client->matchesAppId(isset($payload['merchant_id']) ? (string) $payload['merchant_id'] : null),
            ]),
            'payment.precreate' => $this->success($action, $client->precreate(
                outTradeNo: (string) ($payload['out_trade_no'] ?? ''),
                amount: (float) ($payload['amount'] ?? 0),
                subject: (string) ($payload['subject'] ?? ''),
                timeoutExpress: isset($payload['timeout_express']) ? (string) $payload['timeout_express'] : null,
            )),
            'payment.query' => $this->success($action, $client->query(
                (string) ($payload['out_trade_no'] ?? '')
            )),
            'payment.refund' => $this->success($action, $client->refund(
                outTradeNo: (string) ($payload['out_trade_no'] ?? ''),
                refundAmount: (float) ($payload['refund_amount'] ?? 0),
                refundReason: (string) ($payload['refund_reason'] ?? ''),
                tradeNo: isset($payload['trade_no']) ? (string) $payload['trade_no'] : null,
                outRequestNo: isset($payload['out_request_no']) ? (string) $payload['out_request_no'] : null,
            )),
            'payment.verify_notify' => $this->success($action, [
                'verified' => $client->verifyNotify($payload),
            ]),
            default => $this->unsupported($action),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function client(array $config): AlipayClient
    {
        if ($this->client === null) {
            $this->client = new AlipayClient($config);
        }

        return $this->client;
    }

    private function success(string $action, array $data): array
    {
        return ['success' => true, 'action' => $action, 'data' => $data];
    }

    private function unsupported(string $action): array
    {
        return ['success' => false, 'action' => $action, 'message' => 'Unsupported plugin action', 'data' => []];
    }
}
