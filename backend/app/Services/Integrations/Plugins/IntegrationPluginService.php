<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\IntegrationPlugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegrationPluginService
{
    public function __construct(
        private readonly PluginScanner $scanner,
        private readonly PluginInstaller $installer,
        private readonly PluginConfigRepository $configRepository,
        private readonly PluginRuntimeRegistry $runtimeRegistry,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(?string $domain = null): array
    {
        $manifests = array_values(array_filter(
            $this->scanner->scan($domain),
            fn (PluginManifest $manifest): bool => ! $this->isDemoManifest($manifest),
        ));
        $installedMap = $this->installedPluginMap($domain);

        // 已在文件系统中找到的 domain:slug 键集合
        $scannedKeys = array_flip(array_map(
            fn (PluginManifest $m): string => $m->domain.':'.$m->slug,
            $manifests,
        ));

        $items = array_map(
            fn (PluginManifest $manifest): array => $this->manifestPayload(
                $manifest,
                $installedMap[$manifest->domain.':'.$manifest->slug] ?? null,
            ),
            $manifests,
        );

        // 将已安装但文件目录已丢失的插件追加到列表末尾，带 manifest_missing 标志
        foreach ($installedMap as $key => $plugin) {
            if (! isset($scannedKeys[$key])) {
                $items[] = $this->missingManifestPayload($plugin);
            }
        }

        return $items;
    }

    public function install(string $domain, string $slug): array
    {
        $plugin = $this->installer->install($domain, $slug);

        return $this->detail($plugin);
    }

    public function detail(IntegrationPlugin $plugin): array
    {
        $manifest = $this->scanner->requireManifest((string) $plugin->domain, (string) $plugin->slug);
        $payload = $this->manifestPayload($manifest, $plugin->fresh('config') ?? $plugin);
        $displayConfig = $this->configRepository->displayConfig($plugin->fresh('config') ?? $plugin);

        $payload['config'] = $displayConfig['config'];
        $payload['has_secret_values'] = $displayConfig['has_secret_values'];
        $payload['secret_previews'] = $this->configRepository->secretPreviews($plugin->fresh('config') ?? $plugin, $manifest);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public function updateConfig(IntegrationPlugin $plugin, array $config, ?AdminUser $admin = null): array
    {
        $manifest = $this->scanner->requireManifest((string) $plugin->domain, (string) $plugin->slug);
        $saved = $this->configRepository->save($plugin, $manifest, $config, $admin);

        $payload = $this->detail($plugin->fresh('config') ?? $plugin);
        $payload['config'] = $saved['config'];
        $payload['has_secret_values'] = $saved['has_secret_values'];

        return $payload;
    }

    public function revealConfigSecret(IntegrationPlugin $plugin, string $key): array
    {
        $manifest = $this->scanner->requireManifest((string) $plugin->domain, (string) $plugin->slug);

        return $this->configRepository->revealSecret($plugin, $manifest, $key);
    }

    public function enable(IntegrationPlugin $plugin): array
    {
        return $this->detail($this->installer->enable($plugin));
    }

    public function disable(IntegrationPlugin $plugin): array
    {
        return $this->detail($this->installer->disable($plugin));
    }

    /**
     * @return array<string, mixed>
     */
    public function uninstall(IntegrationPlugin $plugin): array
    {
        $result = [
            'deleted' => false,
            'archived' => false,
            'plugin_id' => (int) $plugin->id,
            'plugin' => null,
        ];

        DB::transaction(function () use ($plugin, &$result): void {
            if ($this->pluginHasBusinessReferences($plugin)) {
                if ($plugin->isEnabled()) {
                    $this->installer->disable($plugin);
                } elseif (Schema::hasColumn('integration_plugins', 'disabled_at') && $plugin->disabled_at === null) {
                    $plugin->forceFill(['disabled_at' => now()])->save();
                }

                $fresh = $plugin->fresh('config') ?? $plugin;
                $result['archived'] = true;
                $result['plugin'] = $this->detail($fresh);

                return;
            }

            if ($plugin->isEnabled()) {
                $this->installer->disable($plugin);
            }
            $plugin->config()->delete();
            $plugin->delete();
            $result['deleted'] = true;
        });

        return $result;
    }

    /**
     * 检测已安装插件中哪些文件目录已丢失。
     * 返回 'domain/slug' => true 的映射，供列表接口附加 manifest_missing 标志。
     *
     * @return array<string, bool>
     */
    public function detectMissingManifests(): array
    {
        if (! Schema::hasTable('integration_plugins')) {
            return [];
        }

        $missing = [];

        IntegrationPlugin::query()->get()->each(function (IntegrationPlugin $plugin) use (&$missing): void {
            $manifest = $this->scanner->find((string) $plugin->domain, (string) $plugin->slug);
            if (! $manifest instanceof PluginManifest) {
                $missing["{$plugin->domain}/{$plugin->slug}"] = true;
            }
        });

        return $missing;
    }

    public function healthCheck(IntegrationPlugin $plugin): array
    {
        if (! Schema::hasTable('integration_plugins')) {
            throw new BusinessException('插件系统尚未初始化', 42200);
        }

        return $this->runtimeRegistry->healthCheck($plugin);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function testEmail(IntegrationPlugin $plugin, array $payload): array
    {
        if (! Schema::hasTable('integration_plugins')) {
            throw new BusinessException('插件系统尚未初始化', 42200);
        }

        if (! $plugin->isEnabled()) {
            throw new BusinessException('插件未启用，无法发送测试邮件', 42200);
        }

        return $this->runtimeRegistry->execute(
            domain: (string) $plugin->domain,
            slugOrKey: (string) $plugin->slug,
            action: 'mail.test_smtp',
            payload: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function testSms(IntegrationPlugin $plugin, array $payload): array
    {
        if (! Schema::hasTable('integration_plugins')) {
            throw new BusinessException('插件系统尚未初始化', 42200);
        }

        if (! $plugin->isEnabled()) {
            throw new BusinessException('插件未启用，无法发送测试短信', 42200);
        }

        return $this->runtimeRegistry->execute(
            domain: (string) $plugin->domain,
            slugOrKey: (string) $plugin->slug,
            action: 'sms.test',
            payload: $payload,
        );
    }

    /**
     * @return array<string, IntegrationPlugin>
     */
    private function installedPluginMap(?string $domain = null): array
    {
        if (! Schema::hasTable('integration_plugins')) {
            return [];
        }

        return IntegrationPlugin::query()
            ->when($domain !== null && trim($domain) !== '', fn ($query) => $query->where('domain', trim($domain)))
            ->get()
            ->keyBy(fn (IntegrationPlugin $plugin): string => $plugin->domain.':'.$plugin->slug)
            ->all();
    }

    private function manifestPayload(PluginManifest $manifest, ?IntegrationPlugin $plugin = null): array
    {
        $referenceCounts = $this->pluginReferenceCounts($plugin);
        $hasReferences = array_sum($referenceCounts) > 0;

        return [
            'id'                       => $plugin?->id,
            'domain'                   => $manifest->domain,
            'slug'                     => $manifest->slug,
            'key'                      => $manifest->key,
            'name'                     => $manifest->name,
            'version'                  => $manifest->version,
            'entry_class'              => $manifest->entryClass,
            'provider_class'           => $manifest->providerClass,
            'capabilities'             => $manifest->capabilities,
            'config_schema'            => $manifest->configSchema,
            'base_path'                => $manifest->basePath,
            'is_installed'             => $plugin instanceof IntegrationPlugin,
            'is_enabled'               => $plugin?->isEnabled() ?? false,
            'status'                   => (int) ($plugin?->status ?? IntegrationPlugin::STATUS_DISABLED),
            'installed_at'             => $plugin?->installed_at?->format('Y-m-d H:i:s'),
            'updated_at'               => $plugin?->updated_at?->format('Y-m-d H:i:s'),
            'binding_counts'           => $referenceCounts,
            'business_reference_count' => array_sum($referenceCounts),
            'delete_mode'              => $plugin instanceof IntegrationPlugin
                ? ($hasReferences ? 'disable_archive' : 'delete')
                : 'not_installed',
            'latest_runtime_log'       => $this->latestRuntimeLog($plugin),
            'manifest_missing'         => false,
        ];
    }

    /**
     * 为文件目录已丢失但仍在数据库中的插件生成列表条目。
     *
     * @return array<string, mixed>
     */
    private function missingManifestPayload(IntegrationPlugin $plugin): array
    {
        $referenceCounts = $this->pluginReferenceCounts($plugin);

        return [
            'id'                       => $plugin->id,
            'domain'                   => $plugin->domain,
            'slug'                     => $plugin->slug,
            'key'                      => $plugin->plugin_key,
            'name'                     => $plugin->name,
            'version'                  => $plugin->version,
            'entry_class'              => $plugin->entry_class,
            'provider_class'           => $plugin->provider_class,
            'capabilities'             => $plugin->capabilities_json ?? [],
            'config_schema'            => [],
            'base_path'                => null,
            'is_installed'             => true,
            'is_enabled'               => $plugin->isEnabled(),
            'status'                   => (int) ($plugin->status ?? IntegrationPlugin::STATUS_DISABLED),
            'installed_at'             => $plugin->installed_at?->format('Y-m-d H:i:s'),
            'updated_at'               => $plugin->updated_at?->format('Y-m-d H:i:s'),
            'binding_counts'           => $referenceCounts,
            'business_reference_count' => array_sum($referenceCounts),
            'delete_mode'              => array_sum($referenceCounts) > 0 ? 'disable_archive' : 'delete',
            'latest_runtime_log'       => $this->latestRuntimeLog($plugin),
            'manifest_missing'         => true,
        ];
    }

    private function isDemoManifest(PluginManifest $manifest): bool
    {
        foreach ([$manifest->slug, $manifest->key] as $identifier) {
            $normalized = strtolower(trim($identifier));
            if ($normalized === 'demo' || str_starts_with($normalized, 'demo_') || str_starts_with($normalized, 'demo-')) {
                return true;
            }
        }

        return str_starts_with(strtolower(trim($manifest->name)), 'demo ');
    }

    private function pluginHasBusinessReferences(IntegrationPlugin $plugin): bool
    {
        $pluginId = (int) $plugin->id;
        if ($pluginId <= 0) {
            return false;
        }

        foreach ($this->pluginReferenceTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'plugin_id')) {
                continue;
            }

            if (DB::table($table)->where('plugin_id', $pluginId)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, int>
     */
    private function pluginReferenceCounts(?IntegrationPlugin $plugin): array
    {
        $pluginId = (int) ($plugin?->id ?? 0);
        if ($pluginId <= 0) {
            return [];
        }

        $counts = [];
        foreach ($this->pluginReferenceTables() as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'plugin_id')) {
                continue;
            }

            $count = DB::table($table)->where('plugin_id', $pluginId)->count();
            if ($count > 0) {
                $counts[$table] = (int) $count;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestRuntimeLog(?IntegrationPlugin $plugin): ?array
    {
        $pluginId = (int) ($plugin?->id ?? 0);
        if ($pluginId <= 0 || ! Schema::hasTable('integration_plugin_runtime_logs')) {
            return null;
        }

        $row = DB::table('integration_plugin_runtime_logs')
            ->where('plugin_id', $pluginId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['id', 'trace_id', 'action', 'status', 'error_message', 'created_at']);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'trace_id' => (string) ($row->trace_id ?? ''),
            'action' => (string) ($row->action ?? ''),
            'status' => (string) ($row->status ?? ''),
            'error_message' => (string) ($row->error_message ?? ''),
            'created_at' => $row->created_at === null ? null : (string) $row->created_at,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function pluginReferenceTables(): array
    {
        return [
            'integration_plugin_bindings',
            'supplier_plugin_bindings',
            'product_upstream_bindings',
            'service_upstream_bindings',
            'service_runtime_snapshots',
            'service_connection_snapshots',
            'service_provision_attempts',
            'integration_plugin_runtime_logs',
            'payments',
            'payment_callbacks',
            'gateway_logs',
            'notification_logs',
            'email_logs',
            'sms_logs',
        ];
    }
}
