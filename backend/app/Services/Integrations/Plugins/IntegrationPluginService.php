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

        return array_map(function (PluginManifest $manifest) use ($installedMap): array {
            $plugin = $installedMap[$manifest->domain.':'.$manifest->slug] ?? null;

            return $this->manifestPayload($manifest, $plugin);
        }, $manifests);
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

    public function enable(IntegrationPlugin $plugin): array
    {
        return $this->detail($this->installer->enable($plugin));
    }

    public function disable(IntegrationPlugin $plugin): array
    {
        return $this->detail($this->installer->disable($plugin));
    }

    public function uninstall(IntegrationPlugin $plugin): void
    {
        DB::transaction(function () use ($plugin): void {
            if ($plugin->isEnabled()) {
                $this->installer->disable($plugin);
            }

            $plugin->config()->delete();
            $plugin->delete();
        });
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
        return [
            'id' => $plugin?->id,
            'domain' => $manifest->domain,
            'slug' => $manifest->slug,
            'key' => $manifest->key,
            'name' => $manifest->name,
            'version' => $manifest->version,
            'entry_class' => $manifest->entryClass,
            'provider_class' => $manifest->providerClass,
            'capabilities' => $manifest->capabilities,
            'config_schema' => $manifest->configSchema,
            'base_path' => $manifest->basePath,
            'is_installed' => $plugin instanceof IntegrationPlugin,
            'is_enabled' => $plugin?->isEnabled() ?? false,
            'status' => (int) ($plugin?->status ?? IntegrationPlugin::STATUS_DISABLED),
            'installed_at' => $plugin?->installed_at?->format('Y-m-d H:i:s'),
            'updated_at' => $plugin?->updated_at?->format('Y-m-d H:i:s'),
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
}
