<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayOperationService;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * 充值 / 在线 / 混合三条支付流的共享网关操作工具：
 * 命名锁、trace_id、网关解析、预下单、查单与支付窗口换算。
 *
 * 使用本 trait 的宿主类必须提供以下属性（构造器注入）：
 *
 * @property ?PaymentGatewayOperationService $gatewayOperations 惰性组装的操作服务
 * @property PaymentGatewayManager $paymentGatewayManager 网关管理器
 * @property ?PaymentGatewayBindingResolver $paymentGatewayBindingResolver 惰性解析的绑定解析器
 * @property CheckoutSecurityService $checkoutSecurityService 支付窗口 TTL 口径
 */
trait PaymentGatewayFlow
{
    protected function resolveTraceId(array $context, string $fallback): string
    {
        $traceId = trim((string) ($context['trace_id'] ?? ''));
        if ($traceId !== '') {
            return substr($traceId, 0, 64);
        }

        return substr(trim($fallback), 0, 64);
    }

    protected function withLock(string $lockKey, int $seconds, callable $callback, string $timeoutMessage): mixed
    {
        try {
            return Cache::lock($lockKey, $seconds)->block(5, $callback);
        } catch (LockTimeoutException) {
            throw new BusinessException($timeoutMessage);
        }
    }

    protected function resolveGateway(string $gateway): PaymentGatewayInterface
    {
        return $this->gatewayOperations()->gateway($gateway);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function paymentCreatePayload(array $payload): array
    {
        return $this->gatewayOperations()->paymentCreatePayload($payload);
    }

    protected function ensurePaymentGatewayAudit(Payment $payment, string $gateway, ?string $traceId = null): void
    {
        $this->gatewayOperations()->ensurePaymentAudit($payment, $gateway, $traceId);
    }

    protected function paymentGatewayBindingResolver(): PaymentGatewayBindingResolver
    {
        return $this->paymentGatewayBindingResolver ??= app(PaymentGatewayBindingResolver::class);
    }

    protected function gatewayOperations(): PaymentGatewayOperationService
    {
        return $this->gatewayOperations ??= new PaymentGatewayOperationService(
            $this->paymentGatewayManager,
            $this->paymentGatewayBindingResolver(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function precreateGatewayPayment(string $gateway, string $outTradeNo, float $amount, string $subject, ?string $timeoutExpress = null, array $context = []): array
    {
        return $this->gatewayOperations()->precreate($gateway, $outTradeNo, $amount, $subject, $timeoutExpress, $context);
    }

    protected function resolvePaymentTimeoutExpress(Payment $payment): string
    {
        // 支付窗口 TTL 统一收敛到 CheckoutSecurityService，避免下单/回调两侧口径漂移
        $deadline = $this->checkoutSecurityService->paymentRecordExpiresAt($payment);

        throw_if($deadline->lessThanOrEqualTo(CarbonImmutable::now()), new BusinessException('支付时间已过期，请重新发起支付'));

        $remainingSeconds = $deadline->diffInSeconds(CarbonImmutable::now(), true);

        return $this->formatGatewayTimeoutExpress($remainingSeconds);
    }

    /**
     * 支付宝 timeout_express 允许 1m~15d，钳制到网关上限避免预下单被拒。
     */
    protected function formatGatewayTimeoutExpress(float $remainingSeconds): string
    {
        $minutes = (int) ceil($remainingSeconds / 60);

        return max(1, min($minutes, 21600)).'m';
    }

    protected function resolveInvoicePaymentTimeoutExpress(Invoice $invoice): string
    {
        // 支付窗口 TTL 统一收敛到 CheckoutSecurityService，避免下单/回调两侧口径漂移
        $deadline = $this->checkoutSecurityService->paymentSessionExpiresAt($invoice);

        throw_if($deadline->lessThanOrEqualTo(CarbonImmutable::now()), new BusinessException('账单支付时间已过期，请重新创建账单'));

        $remainingSeconds = $deadline->diffInSeconds(CarbonImmutable::now(), true);

        return $this->formatGatewayTimeoutExpress($remainingSeconds);
    }

    /**
     * @return array<string, mixed>
     */
    protected function queryGatewayPayment(string $gateway, string $outTradeNo): array
    {
        return $this->gatewayOperations()->query($gateway, $outTradeNo);
    }
}
