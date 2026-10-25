<?php

declare(strict_types=1);

namespace App\Services\Integrations\Payments;

use App\Constants\PaymentGatewayCode;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Contracts\Integrations\PluginIdentity;
use App\Services\Integrations\Plugins\Adapters\PluginPaymentGateway;
use Illuminate\Support\Facades\Log;

final readonly class PaymentGatewayManager
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
    ) {}

    public function gateway(?string $key = null): PaymentGatewayInterface
    {
        $selectedKey = trim((string) ($key ?: config('integrations.payments.default', PaymentGatewayCode::ALIPAY)));

        return $this->registry->get($selectedKey);
    }

    public function alipay(): PaymentGatewayInterface
    {
        return $this->gateway(PaymentGatewayCode::ALIPAY);
    }

    /**
     * @return array<int, PaymentGatewayInterface>
     */
    public function availableThirdPartyGateways(): array
    {
        $available = [];

        foreach ($this->registry->all() as $gateway) {
            $key = PaymentGatewayCode::normalize($gateway->key());
            if (! PaymentGatewayCode::isThirdParty($key)) {
                continue;
            }

            try {
                if (! $gateway->isEnabled()) {
                    continue;
                }
            } catch (\Throwable $exception) {
                Log::warning('[payment] skip unavailable gateway while listing client payment methods', [
                    'gateway' => $key,
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            $available[] = $gateway;
        }

        return $available;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableThirdPartyGatewayOptions(): array
    {
        return collect($this->availableThirdPartyGateways())
            ->flatMap(fn (PaymentGatewayInterface $gateway): array => $this->gatewayPaymentOptions($gateway))
            ->values()
            ->all();
    }

    /**
     * 取单个网关自报的支付方式，供支付创建期解析「通道快照」（P-01/P-03）使用。
     *
     * @return array<int, array<string, mixed>>
     */
    public function paymentOptionsFor(string $gatewayKey): array
    {
        try {
            $gateway = $this->registry->get(PaymentGatewayCode::normalize($gatewayKey));
        } catch (\Throwable) {
            return [];
        }

        return $this->gatewayPaymentOptions($gateway);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function gatewayPaymentOptions(PaymentGatewayInterface $gateway): array
    {
        $key = PaymentGatewayCode::normalize($gateway->key());
        $options = [];

        // 能力位短路（P1-4）：插件网关先看三态声明，明确不支持就不再执行插件；
        // 旧插件（未声明，返回 null）与系统内网关维持既有执行探测。
        $declared = $gateway instanceof PluginPaymentGateway
            ? $gateway->supportsActionDeclaration('payment.options')
            : null;

        $rawOptions = $declared === false ? [] : $this->readPaymentOptions($gateway);

        if ($rawOptions !== []) {
            $options = collect($rawOptions)
                ->map(fn (array $option): ?array => $this->normalizeGatewayOption($option, $key, $gateway))
                ->filter()
                ->values()
                ->all();
        }

        return $options !== [] ? $options : [$this->defaultGatewayOption($key, $gateway)];
    }

    /**
     * 读取网关自报的支付方式（接口层无此方法，故用 method_exists 探测）。
     *
     * @return array<int, array<string, mixed>>
     */
    private function readPaymentOptions(PaymentGatewayInterface $gateway): array
    {
        if (! method_exists($gateway, 'paymentOptions')) {
            return [];
        }

        $raw = $gateway->paymentOptions();

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>|null
     */
    private function normalizeGatewayOption(array $option, string $gatewayKey, PaymentGatewayInterface $gateway): ?array
    {
        $key = PaymentGatewayCode::normalize((string) ($option['key'] ?? $gatewayKey));
        if ($key === '' || ! PaymentGatewayCode::isThirdParty($key)) {
            return null;
        }

        $gatewayName = $gateway->name();
        $name = trim((string) ($option['name'] ?? ''));
        $label = trim((string) ($option['label'] ?? ''));
        $paymentType = trim((string) ($option['payment_type'] ?? ''));
        $optionKey = trim((string) ($option['option_key'] ?? ''));

        if ($optionKey === '') {
            $optionKey = $paymentType !== '' ? "{$key}:{$paymentType}" : $key;
        }

        $payload = [
            'key' => $key,
            'name' => $name !== '' ? $name : ($label !== '' ? $label : $gatewayName),
            'label' => $label !== '' ? $label : ($name !== '' ? $name : PaymentGatewayCode::label($key)),
            'option_key' => $optionKey,
        ];

        if ($paymentType !== '') {
            $payload['payment_type'] = $paymentType;
        }

        return array_merge($payload, $this->pluginIdentityPayload($gateway));
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultGatewayOption(string $key, PaymentGatewayInterface $gateway): array
    {
        return array_merge([
            'key' => $key,
            'name' => $gateway->name(),
            'label' => PaymentGatewayCode::label($key),
            'option_key' => $key,
        ], $this->pluginIdentityPayload($gateway));
    }

    /**
     * 支付方式选项携带插件身份（P-04）：前端与创建链路无需回查 integration_plugins。
     *
     * @return array{plugin_id: int|null, plugin_key: string, plugin_name: string}
     */
    private function pluginIdentityPayload(PaymentGatewayInterface $gateway): array
    {
        if (! $gateway instanceof PluginIdentity) {
            return ['plugin_id' => null, 'plugin_key' => '', 'plugin_name' => ''];
        }

        return [
            'plugin_id' => $gateway->pluginId(),
            'plugin_key' => $gateway->pluginKey(),
            'plugin_name' => $gateway->pluginName(),
        ];
    }
}
