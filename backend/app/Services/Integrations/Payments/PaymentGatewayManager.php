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
     * 选项字段契约（两端各取所需，禁止再用单一拼接串）：
     * - plugin_name：插件名称，只允许管理端展示与日志使用，不得出现在用户端；
     * - channel_name：支付渠道（支付方式）展示名，用户端只读这一个字段；
     * - name / label：历史同义别名，name 恒等于 plugin_name、label 恒等于 channel_name，
     *   供尚未迁移的旧消费方与历史记录回落，不得再承载「易支付 - 支付宝」这类拼接串。
     *
     * @param  array<string, mixed>  $option
     * @return array<string, mixed>|null
     */
    private function normalizeGatewayOption(array $option, string $gatewayKey, PaymentGatewayInterface $gateway): ?array
    {
        $key = PaymentGatewayCode::normalize((string) ($option['key'] ?? $gatewayKey));
        if ($key === '' || ! PaymentGatewayCode::isThirdParty($key)) {
            return null;
        }

        $paymentType = trim((string) ($option['payment_type'] ?? ''));
        $optionKey = trim((string) ($option['option_key'] ?? ''));

        if ($optionKey === '') {
            $optionKey = $paymentType !== '' ? "{$key}:{$paymentType}" : $key;
        }

        // 插件名只认 identity 或网关自报名，渠道名只认插件自报渠道，两者互不回落。
        $identity = $this->pluginIdentityPayload($gateway);
        $pluginName = $this->firstNonEmpty([
            (string) $identity['plugin_name'],
            trim((string) ($option['plugin_name'] ?? '')),
            trim($gateway->name()),
        ]);
        $channelName = $this->firstNonEmpty([
            trim((string) ($option['channel_name'] ?? '')),
            trim((string) ($option['label'] ?? '')),
            trim((string) ($option['name'] ?? '')),
            PaymentGatewayCode::channelLabel($key, $paymentType),
        ]);

        $payload = [
            'key' => $key,
            // name / label 为历史别名：name 跟随插件名，label 跟随渠道名，避免再次出现拼接串。
            'name' => $pluginName,
            'label' => $channelName,
            'plugin_name' => $pluginName,
            'channel_name' => $channelName,
            'plugin_id' => $identity['plugin_id'],
            'plugin_key' => $identity['plugin_key'],
            'option_key' => $optionKey,
        ];

        if ($paymentType !== '') {
            $payload['payment_type'] = $paymentType;
        }

        return $payload;
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function firstNonEmpty(array $values): string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultGatewayOption(string $key, PaymentGatewayInterface $gateway): array
    {
        // 系统内网关（如支付宝当面付）没有渠道声明：name 即插件名，label 即渠道名，两者天然不同。
        $identity = $this->pluginIdentityPayload($gateway);
        $channelName = PaymentGatewayCode::channelLabel($key);
        $pluginName = $this->firstNonEmpty([
            (string) $identity['plugin_name'],
            trim($gateway->name()),
            $channelName,
        ]);

        return [
            'key' => $key,
            'name' => $pluginName,
            'label' => $channelName,
            'plugin_name' => $pluginName,
            'channel_name' => $channelName,
            'plugin_id' => $identity['plugin_id'],
            'plugin_key' => $identity['plugin_key'],
            'option_key' => $key,
        ];
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
