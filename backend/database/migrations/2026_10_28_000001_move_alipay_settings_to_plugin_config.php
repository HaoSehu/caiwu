<?php

declare(strict_types=1);

use App\Models\IntegrationPlugin;
use App\Models\IntegrationPluginConfig;
use App\Models\Setting;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginManifest;
use App\Services\Integrations\Plugins\PluginScanner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * 支付宝配置收敛到插件配置（整体审查 M-41）。
 *
 * 历史原因同一组参数存了两份：settings 表 payment 组（alipay_app_id 等）与
 * integration_plugin_configs（app_id 等），AlipayClient 按「插件配置 > Setting > env」
 * 三级取值，导致管理员在插件页清空密钥后被 Setting 旧值顶回。
 *
 * 本迁移把 settings 表里仅剩的旧值一次性补进插件配置，随后 AlipayClient 不再读
 * settings 表，让插件配置成为唯一真源。
 *
 * 只补空：插件配置里已有值的键一律跳过，不做覆盖；两个密钥按插件声明的 secret
 * 字段规则写入 secret_json（AES 加密）。
 *
 * down() 为空：数据已按新真源落库，回滚代码不需要回写 settings 表；反之强行回写
 * 会覆盖管理员迁移后可能修改过的插件配置。settings 表的旧行保留不动，便于人工核对。
 */
return new class extends Migration
{
    /**
     * settings 组里的旧键 => 插件配置键
     *
     * @var array<string, string>
     */
    private const LEGACY_KEYS = [
        'alipay_app_id' => 'app_id',
        'alipay_private_key' => 'private_key',
        'alipay_public_key' => 'alipay_public_key',
        'alipay_enabled' => 'alipay_enabled',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('integration_plugins') || ! Schema::hasTable('integration_plugin_configs') || ! Schema::hasTable('settings')) {
            return;
        }

        $plugin = IntegrationPlugin::query()
            ->where('domain', 'payment')
            ->where('slug', 'ali_pay')
            ->first();

        if (! $plugin instanceof IntegrationPlugin) {
            return;
        }

        $manifest = app(PluginScanner::class)->find('payment', 'ali_pay');
        if (! $manifest instanceof PluginManifest) {
            return;
        }

        $secretKeys = [];
        foreach ($manifest->configSchema as $item) {
            if ((bool) ($item['secret'] ?? false)) {
                $secretKeys[] = trim((string) ($item['key'] ?? ''));
            }
        }

        $repository = app(PluginConfigRepository::class);
        $resolved = $repository->resolvedConfig($plugin);

        $nonSecretValues = [];
        $secretValues = [];

        foreach (self::LEGACY_KEYS as $settingKey => $configKey) {
            $value = Setting::getValue('payment', $settingKey);
            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            if (! $this->isBlank($resolved[$configKey] ?? null)) {
                continue;
            }

            if (in_array($configKey, $secretKeys, true)) {
                $secretValues[$configKey] = (string) $value;
            } else {
                $nonSecretValues[$configKey] = (string) $value;
            }
        }

        if ($nonSecretValues === [] && $secretValues === []) {
            return;
        }

        $record = IntegrationPluginConfig::query()->firstOrNew(['plugin_id' => $plugin->id]);

        $configJson = is_array($record->config_json) ? $record->config_json : [];
        $record->config_json = array_merge($configJson, $nonSecretValues);

        $existingSecrets = $this->decodeSecrets((string) ($record->secret_json ?? ''));
        $mergedSecrets = array_merge($existingSecrets, $secretValues);
        $record->secret_json = $this->encodeSecrets($mergedSecrets);

        $hasSecretJson = is_array($record->has_secret_json) ? $record->has_secret_json : [];
        foreach ($mergedSecrets as $key => $secretValue) {
            $hasSecretJson[$key] = ! $this->isBlank($secretValue);
        }
        $record->has_secret_json = $hasSecretJson;

        $record->save();
    }

    public function down(): void
    {
        //
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSecrets(string $payload): array
    {
        if (trim($payload) === '') {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $secrets
     */
    private function encodeSecrets(array $secrets): ?string
    {
        if ($secrets === []) {
            return null;
        }

        return Crypt::encryptString((string) json_encode($secrets, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
};
