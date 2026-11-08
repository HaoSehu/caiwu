<?php

namespace App\Services\Auth;

use App\Models\IntegrationPlugin;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginManifest;
use App\Services\Integrations\Plugins\PluginScanner;
use Illuminate\Support\Facades\RateLimiter;

/**
 * 短信/邮件验证码的本地限流。
 *
 * 默认全部关闭：限流等级由插件在 config.php 自己声明（rate_limit_enabled /
 * ip_minute_limit / target_*_limit）。插件未声明这些字段时一律视为不限制，
 * 避免「删掉配置项反而默认开启」——上游运营商（如 MCloud 短信控制台）本身
 * 带有黑名单、同号码频率与账号总量防刷，本地重复限流会误伤正常用户。
 */
class MessageRateLimitService
{
    private const DEFAULT_IP_MINUTE_LIMIT = 0;

    private const DEFAULT_TARGET_MINUTE_LIMIT = 0;

    private const DEFAULT_TARGET_HOUR_LIMIT = 0;

    private const DEFAULT_TARGET_DAY_LIMIT = 0;

    public function __construct(
        private ?IntegrationDriverBindingResolver $bindingResolver = null,
        private ?PluginConfigRepository $configRepository = null,
    ) {}

    /**
     * 插件清单声明键的请求级缓存，键为 plugin_id。
     *
     * @var array<int, list<string>>
     */
    private array $declaredKeysCache = [];

    public function check(string $channel, string $target, ?string $ip = null): array
    {
        $config = $this->getConfig($channel);

        if (! $config['enabled']) {
            return ['ok' => true];
        }

        $ip = $ip ? trim($ip) : null;
        if ($ip && $config['ip_minute_limit'] > 0) {
            $ipMinuteKey = $this->key($channel, 'ip-minute', $ip);
            if (RateLimiter::tooManyAttempts($ipMinuteKey, $config['ip_minute_limit'])) {
                return [
                    'ok' => false,
                    'message' => '当前 IP 每分钟发送次数已达上限，请稍后再试',
                ];
            }
        }

        // 目标号码/邮箱维度限流：防止轮换 IP 对同一目标轰炸
        $target = trim($target);
        if ($target !== '' && $config['target_minute_limit'] > 0) {
            // 与阿里云 dypnsapi SendSmsVerifyCode 每分钟 1 条保持一致，提前拦截避免触发服务商 biz.FREQUENCY
            if (RateLimiter::tooManyAttempts(
                $this->key($channel, 'target-minute', $target),
                $config['target_minute_limit']
            )) {
                return [
                    'ok' => false,
                    'message' => '验证码发送过于频繁，请稍后再试',
                ];
            }
        }

        if ($target !== '' && $config['target_hour_limit'] > 0) {
            if (RateLimiter::tooManyAttempts(
                $this->key($channel, 'target-hour', $target),
                $config['target_hour_limit']
            )) {
                return [
                    'ok' => false,
                    'message' => '该接收方一小时内发送次数已达上限，请稍后再试',
                ];
            }
        }

        if ($target !== '' && $config['target_day_limit'] > 0) {
            if (RateLimiter::tooManyAttempts(
                $this->key($channel, 'target-day', $target),
                $config['target_day_limit']
            )) {
                return [
                    'ok' => false,
                    // 限流 TTL 自首次命中起滚动 24 小时，不严格对齐自然日，避免"明日再试"误导。
                    'message' => '该接收方发送过于频繁，已被临时限制，请稍后再试',
                ];
            }
        }

        return ['ok' => true];
    }

    public function hit(string $channel, string $target, ?string $ip = null): void
    {
        $config = $this->getConfig($channel);

        if (! $config['enabled']) {
            return;
        }

        $ip = $ip ? trim($ip) : null;
        if ($ip && $config['ip_minute_limit'] > 0) {
            RateLimiter::hit(
                $this->key($channel, 'ip-minute', $ip),
                60
            );
        }

        $target = trim($target);
        if ($target !== '' && $config['target_minute_limit'] > 0) {
            RateLimiter::hit(
                $this->key($channel, 'target-minute', $target),
                60
            );
        }

        if ($target !== '' && $config['target_hour_limit'] > 0) {
            RateLimiter::hit(
                $this->key($channel, 'target-hour', $target),
                3600
            );
        }

        if ($target !== '' && $config['target_day_limit'] > 0) {
            RateLimiter::hit(
                $this->key($channel, 'target-day', $target),
                86400
            );
        }
    }

    private function getConfig(string $channel): array
    {
        $pluginConfig = $this->pluginConfigWithId($channel);
        if ($pluginConfig === null) {
            return [
                'enabled' => false,
                'ip_minute_limit' => 0,
                'target_minute_limit' => 0,
                'target_hour_limit' => 0,
                'target_day_limit' => 0,
            ];
        }

        $pluginId = (int) $pluginConfig['__plugin_id'];
        $pluginConfig = $this->onlyDeclaredRateLimitKeys($pluginConfig, $pluginId);

        return [
            // 缺省关闭：未声明 rate_limit_enabled 的插件不启用本地限流
            'enabled' => $this->boolValue($pluginConfig, 'rate_limit_enabled', false),
            'ip_minute_limit' => $this->intValue($pluginConfig, 'ip_minute_limit', self::DEFAULT_IP_MINUTE_LIMIT),
            'target_minute_limit' => $this->intValue($pluginConfig, 'target_minute_limit', self::DEFAULT_TARGET_MINUTE_LIMIT),
            'target_hour_limit' => $this->intValue($pluginConfig, 'target_hour_limit', self::DEFAULT_TARGET_HOUR_LIMIT),
            'target_day_limit' => $this->intValue($pluginConfig, 'target_day_limit', self::DEFAULT_TARGET_DAY_LIMIT),
        ];
    }

    /**
     * 当前渠道绑定的插件配置（含 plugin_id，供 schema 过滤使用）。
     *
     * @return array<string, mixed>|null
     */
    private function pluginConfigWithId(string $channel): ?array
    {
        $context = match ($channel) {
            'email' => $this->bindingResolver()->mailContext(),
            'sms' => $this->bindingResolver()->smsContext(),
            default => null,
        };

        if ($context === null) {
            return null;
        }

        $pluginId = (int) ($context['plugin_id'] ?? 0);
        if ($pluginId <= 0) {
            return null;
        }

        $plugin = IntegrationPlugin::query()->find($pluginId);
        if (! $plugin instanceof IntegrationPlugin || ! $plugin->isEnabled()) {
            return null;
        }

        return ['__plugin_id' => $pluginId] + $this->configRepository()->resolvedConfig($plugin);
    }

    /**
     * 只认插件当前 schema 声明的限流字段。
     *
     * 管理员把限流项从 config.php 移除后，`integration_plugin_configs` 里
     * 的历史值不会被自动删除（保存时按当前 schema 重建，但读取仍会带出旧键）。
     * 若继续按旧值执行，就会出现「界面已经没有这个开关，后台却仍在限流」。
     * 因此这里按插件清单过滤：清单未声明的限流键一律忽略。
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function onlyDeclaredRateLimitKeys(array $config, int $pluginId): array
    {
        $declared = $this->declaredSchemaKeys($pluginId);
        if ($declared === []) {
            return $config;
        }

        foreach (['rate_limit_enabled', 'ip_minute_limit', 'target_minute_limit', 'target_hour_limit', 'target_day_limit'] as $key) {
            if (! in_array($key, $declared, true)) {
                unset($config[$key]);
            }
        }

        return $config;
    }

    /**
     * 插件当前磁盘清单声明的配置键；清单不可读时返回空数组（不过滤）。
     *
     * @return list<string>
     */
    private function declaredSchemaKeys(int $pluginId): array
    {
        if (isset($this->declaredKeysCache[$pluginId])) {
            return $this->declaredKeysCache[$pluginId];
        }

        $keys = [];
        try {
            $plugin = IntegrationPlugin::query()->find($pluginId);
            if ($plugin instanceof IntegrationPlugin) {
                $manifest = app(PluginScanner::class)->find((string) $plugin->domain, (string) $plugin->slug);
                if ($manifest instanceof PluginManifest) {
                    foreach ($manifest->configSchema as $item) {
                        $key = trim((string) ($item['key'] ?? ''));
                        if ($key !== '') {
                            $keys[] = $key;
                        }
                    }
                }
            }
        } catch (\Throwable) {
            return $this->declaredKeysCache[$pluginId] = [];
        }

        return $this->declaredKeysCache[$pluginId] = $keys;
    }

    private function key(string $channel, string $scope, string $identifier): string
    {
        return 'message-rate-limit:'.$channel.':'.$scope.':'.sha1($identifier);
    }

    private function intValue(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? $default;
        $parsed = (int) $value;

        return $parsed >= 0 ? $parsed : $default;
    }

    private function boolValue(array $config, string $key, bool $default): bool
    {
        if (! array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }

    private function bindingResolver(): IntegrationDriverBindingResolver
    {
        return $this->bindingResolver ??= app(IntegrationDriverBindingResolver::class);
    }

    private function configRepository(): PluginConfigRepository
    {
        return $this->configRepository ??= app(PluginConfigRepository::class);
    }
}
