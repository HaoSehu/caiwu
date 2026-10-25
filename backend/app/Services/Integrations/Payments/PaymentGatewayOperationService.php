<?php

declare(strict_types=1);

namespace App\Services\Integrations\Payments;

use App\Constants\PaymentGatewayCode;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Contracts\Integrations\PluginIdentity;
use App\Models\Payment;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;
use Illuminate\Support\Facades\Schema;

class PaymentGatewayOperationService
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly PaymentGatewayBindingResolver $bindings,
    ) {}

    public function gateway(string $gateway): PaymentGatewayInterface
    {
        return $this->gateways->gateway($gateway);
    }

    /**
     * 组装支付单创建负载：剥离调用方传入的网关上下文，写入插件与通道快照列（P-01/P-03）。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function paymentCreatePayload(array $payload): array
    {
        $gateway = (string) ($payload['gateway_key'] ?? $payload['gateway'] ?? '');
        $paymentType = trim((string) ($payload['payment_type'] ?? ''));
        unset($payload['gateway'], $payload['payment_type']);

        return array_merge($payload, $this->auditColumnsForGateway($gateway, $paymentType));
    }

    /**
     * 复用旧支付单时补齐插件与通道审计列（P-03）：只填空值，不覆盖已落库的历史事实。
     */
    public function ensurePaymentAudit(Payment $payment, string $gateway, ?string $traceId = null): void
    {
        $payload = [];
        $context = $this->bindings->contextForPayment($payment);
        $snapshot = $this->snapshotColumnsFor($gateway, $this->storedPaymentType($payment));

        if ($context['plugin_id'] !== null && (int) ($payment->plugin_id ?? 0) <= 0 && Schema::hasColumn('payments', 'plugin_id')) {
            $payload['plugin_id'] = $context['plugin_id'];
        }

        $gatewayKey = $context['gateway_key'] ?: $this->bindings->normalizeGatewayKey($gateway);
        if ($gatewayKey !== '' && trim((string) ($payment->gateway_key ?? '')) === '' && Schema::hasColumn('payments', 'gateway_key')) {
            $payload['gateway_key'] = $gatewayKey;
        }

        foreach ($snapshot as $column => $value) {
            if ($column === 'plugin_id' || $value === null || $value === '') {
                continue;
            }

            if (trim((string) ($payment->getAttribute($column) ?? '')) !== '') {
                continue;
            }

            if (! Schema::hasColumn('payments', $column)) {
                continue;
            }

            $payload[$column] = $value;
        }

        $resolvedTraceId = trim((string) ($traceId ?? ''));
        if ($resolvedTraceId !== '' && trim((string) ($payment->trace_id ?? '')) === '') {
            $payload['trace_id'] = $resolvedTraceId;
        }

        if ($payload !== []) {
            $payment->forceFill($payload)->save();
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function precreate(
        string $gateway,
        string $outTradeNo,
        float $amount,
        string $subject,
        ?string $timeoutExpress = null,
        array $context = [],
    ): array {
        return $this->gateway($gateway)
            ->precreate(new PaymentPrecreateRequest(
                outTradeNo: $outTradeNo,
                amount: $amount,
                subject: $subject,
                timeoutExpress: $timeoutExpress,
                context: $context,
            ))
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function query(string $gateway, string $outTradeNo): array
    {
        return $this->gateway($gateway)->query($outTradeNo)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function auditColumnsForGateway(string $gateway, string $paymentType = ''): array
    {
        $context = $this->bindings->contextForGateway($gateway);
        $snapshot = $this->snapshotColumnsFor($gateway, $paymentType);
        $columns = [];

        if (Schema::hasColumn('payments', 'plugin_id')) {
            $columns['plugin_id'] = $snapshot['plugin_id'] ?? $context['plugin_id'];
        }

        if (Schema::hasColumn('payments', 'gateway_key')) {
            $columns['gateway_key'] = $context['gateway_key'];
        }

        foreach (['plugin_key', 'plugin_name', 'payment_type', 'option_key', 'option_name'] as $column) {
            if (! Schema::hasColumn('payments', $column)) {
                continue;
            }

            $value = $snapshot[$column] ?? null;
            if ($value !== null && $value !== '') {
                $columns[$column] = $value;
            }
        }

        return $columns;
    }

    /**
     * 插件与通道快照：优先取当前网关实例自报的身份（A-01，无需回查），
     * 实例未实现 PluginIdentity 或取不到时回退绑定解析器（历史回填与回调兜底路径）。
     *
     * @return array{plugin_id: int|null, plugin_key: string|null, plugin_name: string|null, payment_type: string|null, option_key: string|null, option_name: string|null}
     */
    private function snapshotColumnsFor(string $gateway, string $paymentType = ''): array
    {
        $gatewayKey = $this->bindings->normalizeGatewayKey($gateway);
        if ($gatewayKey === '') {
            $gatewayKey = trim($gateway);
        }

        $identity = $this->identityForGateway($gateway);
        $stored = $this->bindings->pluginSnapshotForGateway($gateway);

        $pluginId = $identity?->pluginId() ?? $stored['plugin_id'];
        $pluginKey = $this->firstNonEmpty([$identity?->pluginKey(), $stored['plugin_key']]);
        $pluginName = $this->firstNonEmpty([$identity?->pluginName(), $stored['plugin_name']]);

        $paymentType = trim($paymentType);
        $option = $this->optionForPaymentType($gateway, $paymentType);
        if ($paymentType === '') {
            $paymentType = trim((string) ($option['payment_type'] ?? ''));
        }

        $optionKey = trim((string) ($option['option_key'] ?? ''));
        if ($optionKey === '') {
            $optionKey = $paymentType !== '' && $gatewayKey !== '' ? "{$gatewayKey}:{$paymentType}" : $gatewayKey;
        }

        $optionName = trim((string) ($option['name'] ?? ''));
        if ($optionName === '') {
            $optionName = $pluginName !== null && $pluginName !== '' ? $pluginName : PaymentGatewayCode::label($gatewayKey);
        }

        return [
            'plugin_id' => $pluginId,
            'plugin_key' => $pluginKey,
            'plugin_name' => $pluginName,
            'payment_type' => $paymentType !== '' ? $paymentType : null,
            'option_key' => $optionKey !== '' ? $optionKey : null,
            'option_name' => $optionName !== '' ? $optionName : null,
        ];
    }

    private function identityForGateway(string $gateway): ?PluginIdentity
    {
        try {
            $instance = $this->gateways->gateway($gateway);
        } catch (\Throwable) {
            return null;
        }

        return $instance instanceof PluginIdentity ? $instance : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function optionForPaymentType(string $gateway, string $paymentType): ?array
    {
        if ($paymentType === '') {
            return null;
        }

        try {
            $options = $this->gateways->paymentOptionsFor($gateway);
        } catch (\Throwable) {
            return null;
        }

        foreach ($options as $option) {
            if (trim((string) ($option['payment_type'] ?? '')) === $paymentType) {
                return $option;
            }
        }

        return null;
    }

    /**
     * 从支付单既有数据还原通道类型，供复用旧单补齐快照使用（P-09 之后以独立列为准）。
     */
    private function storedPaymentType(Payment $payment): string
    {
        $paymentType = trim((string) ($payment->payment_type ?? ''));
        if ($paymentType !== '') {
            return $paymentType;
        }

        $raw = (array) ($payment->callback_raw ?? []);
        $context = $raw['payment_context'] ?? null;

        if (is_array($context)) {
            return trim((string) ($context['payment_type'] ?? ''));
        }

        return trim((string) ($raw['payment_type'] ?? ''));
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) ($value ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
