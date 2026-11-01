<?php

declare(strict_types=1);

namespace App\Services\Content\Storage;

use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use App\Models\Setting;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Support\SchemaMetadataCache;
use Illuminate\Support\Facades\Log;

/**
 * 媒体库存储驱动解析器。
 *
 * 三层选择（优先级从高到低）：
 * 1. 单文件级：上传请求可选传入 driver；
 * 2. 分组策略级：settings 组 media 的 policy_json（{"default":"local","groups":{"hero-videos":"qiniu"}}）；
 * 3. 全局默认级：settings 组 media 的 storage_driver。
 *
 * 解析不到、值为空或对应插件未启用/配置不全时一律回落 local，
 * 保证「远程链路不可用不影响媒体库可用」。
 */
class MediaStorageResolver
{
    public const LOCAL_DRIVER = 'local';

    public const SETTING_GROUP = 'media';

    public const SETTING_KEY_STORAGE_DRIVER = 'storage_driver';

    public const SETTING_KEY_POLICY_JSON = 'policy_json';

    private const DRIVER_PATTERN = '/^[a-z0-9_-]{1,24}$/';

    public function __construct(
        private readonly PluginScanner $scanner,
        private readonly PluginConfigRepository $configRepository,
    ) {}

    /**
     * 解析当前上传应使用的存储驱动。
     */
    public function resolve(string $group, ?string $requestedDriver = null): string
    {
        $candidate = trim((string) $requestedDriver);

        if ($candidate === '') {
            $candidate = $this->resolvePolicyDriver($group);
        }

        if ($candidate === '' || $candidate === self::LOCAL_DRIVER) {
            return self::LOCAL_DRIVER;
        }

        if (! $this->isRemoteDriverAvailable($candidate)) {
            Log::warning('[media-storage] 存储驱动不可用，已回落本地存储', [
                'group' => $group,
                'driver' => $candidate,
            ]);

            return self::LOCAL_DRIVER;
        }

        return $candidate;
    }

    /**
     * 远程驱动是否同时满足「已安装启用」与「必填配置齐全」。
     */
    public function isRemoteDriverAvailable(string $driver): bool
    {
        $slug = trim($driver);

        if ($slug === '' || $slug === self::LOCAL_DRIVER || preg_match(self::DRIVER_PATTERN, $slug) !== 1) {
            return false;
        }

        if (! SchemaMetadataCache::hasTable('integration_plugins')) {
            return false;
        }

        $plugin = IntegrationPlugin::query()
            ->where('domain', PluginDomain::ADDONS)
            ->where('slug', $slug)
            ->first();

        if (! $plugin instanceof IntegrationPlugin || ! $plugin->isEnabled()) {
            return false;
        }

        try {
            $manifest = $this->scanner->requireManifest(PluginDomain::ADDONS, $slug);
            $this->configRepository->assertConfigReady($plugin, $manifest);
        } catch (\Throwable) {
            // 目录缺失、清单损坏、必填项缺失均视为不可用，由调用方回落本地。
            return false;
        }

        return true;
    }

    /**
     * 当前可用的驱动候选：local + 已启用且声明 media.storage 能力的 addons 插件。
     *
     * @return array<int, string>
     */
    public function availableDrivers(): array
    {
        $drivers = [self::LOCAL_DRIVER];

        if (! SchemaMetadataCache::hasTable('integration_plugins')) {
            return $drivers;
        }

        $plugins = IntegrationPlugin::query()
            ->where('domain', PluginDomain::ADDONS)
            ->get(['slug', 'status', 'capabilities_json']);

        foreach ($plugins as $plugin) {
            if (! $plugin->isEnabled()) {
                continue;
            }

            $capabilities = is_array($plugin->capabilities_json) ? $plugin->capabilities_json : [];
            if (! in_array('media.storage', $capabilities, true)) {
                continue;
            }

            $slug = trim((string) $plugin->slug);
            if ($slug !== '' && ! in_array($slug, $drivers, true)) {
                $drivers[] = $slug;
            }
        }

        return $drivers;
    }

    /**
     * 可用驱动的完整信息（slug + 显示名），供媒体库前端渲染存储区标签与上传目标选择。
     *
     * 过滤口径与 availableDrivers() 完全一致：local 固定在首位，
     * 远程候选须「已启用 + capabilities 含 media.storage」。
     *
     * @return array<int, array{slug: string, name: string}>
     */
    public function availableDriverDetails(): array
    {
        $details = [
            ['slug' => self::LOCAL_DRIVER, 'name' => '本地存储'],
        ];

        foreach ($this->availableDrivers() as $slug) {
            if ($slug === self::LOCAL_DRIVER) {
                continue;
            }

            $name = trim((string) IntegrationPlugin::query()
                ->where('domain', PluginDomain::ADDONS)
                ->where('slug', $slug)
                ->value('name'));

            $details[] = [
                'slug' => $slug,
                'name' => $name !== '' ? $name : $slug,
            ];
        }

        return $details;
    }

    /**
     * 断言远程驱动可用，不可用直接抛业务异常（迁移等显式指定驱动的入口使用）。
     */
    public function assertDriverAvailable(string $driver): void
    {
        if (! $this->isRemoteDriverAvailable($driver)) {
            throw new BusinessException('远程存储驱动不可用或未启用：'.trim($driver), 42200);
        }
    }

    /**
     * 按分组策略解析默认驱动，返回空串表示未配置策略。
     */
    private function resolvePolicyDriver(string $group): string
    {
        $policyRaw = Setting::getValue(self::SETTING_GROUP, self::SETTING_KEY_POLICY_JSON, '');
        $policy = is_string($policyRaw) && $policyRaw !== ''
            ? json_decode($policyRaw, true)
            : null;

        if (is_array($policy)) {
            $groups = is_array($policy['groups'] ?? null) ? $policy['groups'] : [];
            $candidate = trim((string) ($groups[$group] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }

            $default = trim((string) ($policy['default'] ?? ''));
            if ($default !== '') {
                return $default;
            }
        }

        return trim((string) Setting::getValue(
            self::SETTING_GROUP,
            self::SETTING_KEY_STORAGE_DRIVER,
            self::LOCAL_DRIVER
        ));
    }
}
