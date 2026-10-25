<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use App\Constants\PaymentGatewayCode;
use App\Models\Payment;
use App\Support\SchemaMetadataCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentGatewayBindingResolver
{
    /**
     * @return array{plugin_id: int|null, gateway_key: string|null}
     */
    public function contextForGateway(string $gateway): array
    {
        $gatewayKey = $this->normalizeGatewayKey($gateway);

        return [
            'plugin_id' => $this->pluginIdForGatewayKey($gatewayKey),
            'gateway_key' => $gatewayKey !== '' ? $gatewayKey : null,
        ];
    }

    /**
     * @return array{plugin_id: int|null, gateway_key: string|null}
     */
    public function contextForPayment(Payment $payment): array
    {
        $gatewayKey = $this->normalizeGatewayKey($payment->gatewayKey());
        $pluginId = (int) ($payment->plugin_id ?? 0);

        return [
            'plugin_id' => $pluginId > 0 ? $pluginId : $this->pluginIdForGatewayKey($gatewayKey),
            'gateway_key' => $gatewayKey !== '' ? $gatewayKey : null,
        ];
    }

    public function normalizeGatewayKey(string $gateway): string
    {
        return PaymentGatewayCode::normalize($gateway);
    }

    /**
     * 按支付网关标识取插件身份快照，供「历史回填 / 回调兜底 / 运行日志」等无法拿到
     * 网关实例的链路使用。创建期一律以网关实例的 PluginIdentity 为准（见 P-03）。
     *
     * @return array{plugin_id: int|null, plugin_key: string|null, plugin_name: string|null}
     */
    public function pluginSnapshotForGateway(string $gateway): array
    {
        return $this->pluginSnapshotForGatewayKey($this->normalizeGatewayKey($gateway));
    }

    /**
     * @return array{plugin_id: int|null, plugin_key: string|null, plugin_name: string|null}
     */
    public function pluginSnapshotForPayment(Payment $payment): array
    {
        $pluginId = (int) ($payment->plugin_id ?? 0);

        if ($pluginId > 0) {
            return [
                'plugin_id' => $pluginId,
                'plugin_key' => $this->nullableString($payment->plugin_key),
                'plugin_name' => $this->nullableString($payment->plugin_name),
            ];
        }

        return $this->pluginSnapshotForGatewayKey($this->normalizeGatewayKey($payment->gatewayKey()));
    }

    private function pluginIdForGatewayKey(string $gatewayKey): ?int
    {
        $snapshot = $this->pluginSnapshotForGatewayKey($gatewayKey);

        return $snapshot['plugin_id'];
    }

    /**
     * 只按 plugin_key 匹配（(domain, plugin_key) 唯一）。slug 是插件目录名，
     * 与 plugin_key 属于两套标识，不再作为兜底匹配条件（A-03）。
     *
     * @return array{plugin_id: int|null, plugin_key: string|null, plugin_name: string|null}
     */
    private function pluginSnapshotForGatewayKey(string $gatewayKey): array
    {
        $empty = ['plugin_id' => null, 'plugin_key' => null, 'plugin_name' => null];

        if ($gatewayKey === '' || ! PaymentGatewayCode::isThirdParty($gatewayKey)) {
            return $empty;
        }

        if (! SchemaMetadataCache::hasTable('integration_plugins')) {
            return $empty;
        }

        $plugins = DB::table('integration_plugins')
            ->where('domain', PluginDomain::PAYMENT)
            ->where('plugin_key', $gatewayKey)
            ->orderByDesc('status')
            ->orderBy('id')
            ->limit(2)
            ->get(['id', 'plugin_key', 'name']);

        if ($plugins->count() > 1) {
            // 同一 plugin_key 命中多条说明数据本身异常：不猜测，置空并留痕。
            Log::warning('[payment] ambiguous plugin identity for gateway key', [
                'gateway_key' => $gatewayKey,
                'plugin_ids' => $plugins->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
            ]);

            return $empty;
        }

        $plugin = $plugins->first();
        if ($plugin === null) {
            return $empty;
        }

        return [
            'plugin_id' => (int) $plugin->id,
            'plugin_key' => $this->nullableString($plugin->plugin_key ?? null),
            'plugin_name' => $this->nullableString($plugin->name ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
