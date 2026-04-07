<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CheckoutSecurityService
{
    private const QUOTE_TTL_SECONDS = 600;
    private const ORDER_IDEMPOTENCY_TTL_SECONDS = 900;
    private const ORDER_FINGERPRINT_TTL_SECONDS = 180;
    private const PAYMENT_SESSION_TTL_SECONDS = 1800;
    private const PAYMENT_POLL_TTL_SECONDS = 1800;
    private const RECHARGE_POLL_TTL_SECONDS = 1800;

    public function issueQuoteToken(
        int $productId,
        string $billingCycle,
        array $config,
        array $quotePayload,
        array $context = [],
    ): array {
        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds(self::QUOTE_TTL_SECONDS);
        $token = $this->generateToken('quote');

        Cache::put($this->quoteCacheKey($token), [
            'product_id' => $productId,
            'billing_cycle' => $billingCycle,
            'quantity' => max((int) ($quotePayload['quantity'] ?? 1), 1),
            'config_hash' => $this->hashPayload($config),
            'original_amount' => $this->normalizeAmount($quotePayload['subtotal_amount'] ?? $quotePayload['total_amount'] ?? 0),
            'amount' => $this->normalizeAmount($quotePayload['total_amount'] ?? 0),
            'base_amount' => $this->normalizeAmount($quotePayload['base_amount'] ?? 0),
            'config_amount' => $this->normalizeAmount($quotePayload['config_amount'] ?? 0),
            'setup_fee' => $this->normalizeAmount($quotePayload['setup_fee'] ?? 0),
            'discount_amount' => $this->normalizeAmount($quotePayload['discount_amount'] ?? 0),
            'user_coupon_id' => (int) ($quotePayload['coupon']['user_coupon_id'] ?? $quotePayload['user_coupon_id'] ?? 0),
            'request_id' => trim((string) ($context['request_id'] ?? '')),
            'ip_address' => trim((string) ($context['ip_address'] ?? '')),
            'issued_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'quote_token' => $token,
            'quote_expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function assertQuoteToken(
        string $token,
        int $productId,
        string $billingCycle,
        int $quantity,
        array $config,
        string $originalAmount,
        string $amount,
        ?int $couponId = null,
    ): array {
        $payload = Cache::get($this->quoteCacheKey($token));

        throw_if(! is_array($payload), new BusinessException('报价凭证已失效，请刷新配置后重试'));

        $tokenProductId = (int) ($payload['product_id'] ?? 0);
        $tokenBillingCycle = (string) ($payload['billing_cycle'] ?? '');
        $tokenQuantity = max((int) ($payload['quantity'] ?? 1), 1);
        $tokenConfigHash = (string) ($payload['config_hash'] ?? '');
        $tokenOriginalAmount = $this->normalizeAmount($payload['original_amount'] ?? 0);
        $tokenAmount = $this->normalizeAmount($payload['amount'] ?? 0);
        $tokenCouponId = (int) ($payload['user_coupon_id'] ?? 0);

        throw_if(
            $tokenProductId !== $productId
            || $tokenBillingCycle !== $billingCycle
            || $tokenQuantity !== max($quantity, 1)
            || $tokenConfigHash !== $this->hashPayload($config),
            new BusinessException('订单配置与报价不一致，请重新获取报价')
        );

        throw_if(
            $tokenOriginalAmount !== $this->normalizeAmount($originalAmount)
            || $tokenCouponId !== (int) ($couponId ?? 0)
            || $tokenAmount !== $this->normalizeAmount($amount),
            new BusinessException('报价已变更，请刷新页面后重试')
        );

        return $payload;
    }

    public function buildCheckoutFingerprint(int $productId, string $billingCycle, int $quantity, array $config, int $couponId = 0): string
    {
        return hash('sha256', implode('|', [
            $productId,
            $billingCycle,
            max($quantity, 1),
            $this->hashPayload($config),
            (int) $couponId,
        ]));
    }

    public function rememberCreatedOrder(
        int $userId,
        string $idempotencyKey,
        string $fingerprint,
        int $orderId,
    ): void {
        $idempotencyExpiresAt = CarbonImmutable::now()->addSeconds(self::ORDER_IDEMPOTENCY_TTL_SECONDS);
        $fingerprintExpiresAt = CarbonImmutable::now()->addSeconds(self::ORDER_FINGERPRINT_TTL_SECONDS);

        Cache::put($this->orderIdempotencyCacheKey($userId, $idempotencyKey), $orderId, $idempotencyExpiresAt);
        Cache::put($this->orderFingerprintCacheKey($userId, $fingerprint), $orderId, $fingerprintExpiresAt);
    }

    public function resolveIdempotentOrderId(int $userId, string $idempotencyKey): ?int
    {
        $orderId = (int) Cache::get($this->orderIdempotencyCacheKey($userId, $idempotencyKey), 0);

        return $orderId > 0 ? $orderId : null;
    }

    public function resolveFingerprintOrderId(int $userId, string $fingerprint): ?int
    {
        $orderId = (int) Cache::get($this->orderFingerprintCacheKey($userId, $fingerprint), 0);

        return $orderId > 0 ? $orderId : null;
    }

    public function issuePaymentSession(Order $order, int $userId): array
    {
        $invoiceId = (int) ($order->invoice?->id ?? 0);
        if ($invoiceId <= 0) {
            return [
                'session_token' => '',
                'expires_at' => null,
            ];
        }

        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds(self::PAYMENT_SESSION_TTL_SECONDS);
        $token = $this->generateToken('pay_session');

        Cache::put($this->paymentSessionCacheKey($token), [
            'order_id' => (int) $order->id,
            'user_id' => $userId,
            'invoice_id' => $invoiceId,
            'order_status' => (int) $order->status,
            'invoice_status' => (int) ($order->invoice?->status ?? 0),
            'issued_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'session_token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function assertPaymentSessionToken(string $token, Order $order, int $userId): array
    {
        $payload = Cache::get($this->paymentSessionCacheKey($token));

        throw_if(! is_array($payload), new BusinessException('支付会话已失效，请刷新页面后重试'));

        throw_if(
            (int) ($payload['order_id'] ?? 0) !== (int) $order->id
            || (int) ($payload['user_id'] ?? 0) !== $userId
            || (int) ($payload['invoice_id'] ?? 0) !== (int) ($order->invoice?->id ?? 0),
            new BusinessException('支付会话校验失败，请刷新页面后重试')
        );

        return $payload;
    }

    public function issuePaymentPollToken(Payment $payment, Order $order, int $userId): array
    {
        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds(self::PAYMENT_POLL_TTL_SECONDS);
        $token = $this->generateToken('pay_poll');

        Cache::put($this->paymentPollCacheKey($token), [
            'payment_id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'order_id' => (int) $order->id,
            'invoice_id' => (int) ($order->invoice?->id ?? 0),
            'user_id' => $userId,
            'gateway' => (string) $payment->gateway,
            'issued_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'poll_token' => $token,
            'poll_expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function assertPaymentPollToken(string $token, Payment $payment, Order $order, int $userId): array
    {
        $payload = Cache::get($this->paymentPollCacheKey($token));

        throw_if(! is_array($payload), new BusinessException('支付轮询凭证已失效，请重新获取二维码'));

        throw_if(
            (int) ($payload['payment_id'] ?? 0) !== (int) $payment->id
            || (string) ($payload['payment_no'] ?? '') !== (string) $payment->payment_no
            || (int) ($payload['order_id'] ?? 0) !== (int) $order->id
            || (int) ($payload['invoice_id'] ?? 0) !== (int) ($order->invoice?->id ?? 0)
            || (int) ($payload['user_id'] ?? 0) !== $userId,
            new BusinessException('支付轮询凭证校验失败，请重新获取二维码')
        );

        return $payload;
    }

    public function issueRechargePollToken(Payment $payment, int $userId): array
    {
        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds(self::RECHARGE_POLL_TTL_SECONDS);
        $token = $this->generateToken('recharge_poll');

        Cache::put($this->rechargePollCacheKey($token), [
            'payment_id' => (int) $payment->id,
            'payment_no' => (string) $payment->payment_no,
            'user_id' => $userId,
            'gateway' => (string) $payment->gateway,
            'issued_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
        ], $expiresAt);

        return [
            'poll_token' => $token,
            'poll_expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    public function assertRechargePollToken(string $token, Payment $payment, int $userId): array
    {
        $payload = Cache::get($this->rechargePollCacheKey($token));

        throw_if(! is_array($payload), new BusinessException('充值轮询凭证已失效，请重新获取二维码'));

        throw_if(
            (int) ($payload['payment_id'] ?? 0) !== (int) $payment->id
            || (string) ($payload['payment_no'] ?? '') !== (string) $payment->payment_no
            || (int) ($payload['user_id'] ?? 0) !== $userId,
            new BusinessException('充值轮询凭证校验失败，请重新获取二维码')
        );

        return $payload;
    }

    private function quoteCacheKey(string $token): string
    {
        return 'checkout:quote:' . $token;
    }

    private function orderIdempotencyCacheKey(int $userId, string $idempotencyKey): string
    {
        return 'checkout:order:idempotency:' . $userId . ':' . sha1($idempotencyKey);
    }

    private function orderFingerprintCacheKey(int $userId, string $fingerprint): string
    {
        return 'checkout:order:fingerprint:' . $userId . ':' . $fingerprint;
    }

    private function paymentSessionCacheKey(string $token): string
    {
        return 'checkout:payment:session:' . $token;
    }

    private function paymentPollCacheKey(string $token): string
    {
        return 'checkout:payment:poll:' . $token;
    }

    private function rechargePollCacheKey(string $token): string
    {
        return 'checkout:recharge:poll:' . $token;
    }

    private function generateToken(string $prefix): string
    {
        return $prefix . '_' . Str::lower(Str::random(48));
    }

    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($this->sortPayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function sortPayload(array $payload): array
    {
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->sortPayload($value);
                continue;
            }

            if (is_bool($value)) {
                $payload[$key] = $value ? 1 : 0;
                continue;
            }

            if ($value === null) {
                $payload[$key] = '';
                continue;
            }

            if (is_numeric($value)) {
                $payload[$key] = (string) $value;
                continue;
            }

            $payload[$key] = trim((string) $value);
        }

        return $payload;
    }

    private function normalizeAmount(mixed $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
