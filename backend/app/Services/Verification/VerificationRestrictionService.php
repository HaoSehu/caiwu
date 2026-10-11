<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * M11 未实名自动限制：settings 配置读写（key=verification.restrictions，JSON 存储）。
 *
 * 裁决默认值（2026-10-11）：
 * - enabled 默认 false——上线不自动拦截，需管理员在设置面板显式开启；
 * - scope：purchase / recharge / withdrawal / service_transfer_receive 默认开启，
 *   renewal 默认关闭；
 * - grace_days=0（注册后无宽限）；exempt_member_level_ids 默认空。
 */
class VerificationRestrictionService
{
    public const SETTING_GROUP = 'verification';

    public const SETTING_KEY = 'restrictions';

    /** @var list<string> */
    public const SCOPES = ['purchase', 'recharge', 'withdrawal', 'service_transfer_receive', 'renewal'];

    /**
     * @return array{enabled: bool, grace_days: int, exempt_member_level_ids: list<int>, scope: array<string, bool>}
     */
    public function defaults(): array
    {
        return [
            // 裁决：总开关默认关闭，上线不自动拦截；由管理员显式开启。
            'enabled' => false,
            'grace_days' => 0,
            'exempt_member_level_ids' => [],
            'scope' => [
                'purchase' => true,
                'recharge' => true,
                'renewal' => false,
                'service_transfer_receive' => true,
                'withdrawal' => true,
            ],
        ];
    }

    /**
     * 读取合并默认值后的完整配置。
     *
     * @return array{enabled: bool, grace_days: int, exempt_member_level_ids: list<int>, scope: array<string, bool>}
     */
    public function get(): array
    {
        $defaults = $this->defaults();
        $raw = Setting::getValue(self::SETTING_GROUP, self::SETTING_KEY, '');

        if (! is_string($raw) || trim($raw) === '') {
            return $defaults;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            Log::warning('[M11] verification.restrictions 配置解析失败，回退默认值');

            return $defaults;
        }

        if (! is_array($decoded)) {
            return $defaults;
        }

        $scope = $defaults['scope'];
        foreach (array_keys($scope) as $name) {
            if (array_key_exists($name, $decoded['scope'] ?? []) && is_bool($decoded['scope'][$name])) {
                $scope[$name] = $decoded['scope'][$name];
            }
        }

        return [
            'enabled' => in_array($decoded['enabled'] ?? false, [true, 1, '1', 'true', 'on'], true),
            'grace_days' => max(0, min(365, (int) ($decoded['grace_days'] ?? $defaults['grace_days']))),
            'exempt_member_level_ids' => array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                array_filter(
                    is_array($decoded['exempt_member_level_ids'] ?? null) ? $decoded['exempt_member_level_ids'] : [],
                    static fn ($id): bool => is_numeric($id) && (int) $id > 0
                )
            ))),
            'scope' => $scope,
        ];
    }

    /**
     * 保存配置（走 settings 存储键 verification.restrictions）。
     *
     * @param  array<string, mixed>  $config
     */
    public function save(array $config): void
    {
        $current = $this->get();

        $scope = $current['scope'];
        foreach (array_keys($scope) as $name) {
            if (array_key_exists($name, $config['scope'] ?? []) && is_bool($config['scope'][$name])) {
                $scope[$name] = $config['scope'][$name];
            }
        }

        $payload = [
            'enabled' => in_array($config['enabled'] ?? $current['enabled'], [true, 1, '1', 'true', 'on'], true),
            'grace_days' => max(0, min(365, (int) ($config['grace_days'] ?? $current['grace_days']))),
            'exempt_member_level_ids' => array_values(array_unique(array_map(
                static fn ($id): int => (int) $id,
                array_filter(
                    is_array($config['exempt_member_level_ids'] ?? null) ? $config['exempt_member_level_ids'] : $current['exempt_member_level_ids'],
                    static fn ($id): bool => is_numeric($id) && (int) $id > 0
                )
            ))),
            'scope' => $scope,
        ];

        Setting::setValue(
            self::SETTING_GROUP,
            self::SETTING_KEY,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }
}
