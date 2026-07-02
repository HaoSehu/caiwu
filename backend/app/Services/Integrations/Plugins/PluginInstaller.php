<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use App\Models\Setting;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Mail\MailDriverManager;
use App\Services\Sms\SmsDriverManager;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use App\Services\Verification\VerificationDriverManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

class PluginInstaller
{
    public function __construct(
        private readonly PluginScanner $scanner,
        private readonly PluginFileLoader $fileLoader,
        private readonly PluginConfigRepository $configRepository,
        private readonly Container $container,
    ) {}

    public function install(string $domain, string $slug): IntegrationPlugin
    {
        $manifest = $this->scanner->requireManifest($domain, $slug);
        $this->assertManifestExecutable($manifest);

        $plugin = DB::transaction(function () use ($manifest): IntegrationPlugin {
            return IntegrationPlugin::query()->updateOrCreate(
                [
                    'domain' => $manifest->domain,
                    'slug' => $manifest->slug,
                ],
                [
                    'plugin_key' => $manifest->key,
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'provider_class' => $manifest->providerClass,
                    'entry_class' => $manifest->entryClass,
                    'capabilities_json' => $manifest->capabilities,
                    'config_schema_json' => $manifest->configSchema,
                    'installed_at' => now(),
                ]
            );
        });

        $this->forgetDomainRuntime($manifest->domain);

        return $plugin;
    }

    public function enable(IntegrationPlugin $plugin): IntegrationPlugin
    {
        $manifest = $this->scanner->requireManifest((string) $plugin->domain, (string) $plugin->slug);
        $this->assertManifestExecutable($manifest);
        $this->configRepository->assertConfigReady($plugin, $manifest);

        $plugin->forceFill([
            'status' => IntegrationPlugin::STATUS_ENABLED,
        ])->save();

        $this->syncSelectionSetting($manifest, true);
        $this->forgetDomainRuntime($manifest->domain);

        return $plugin->fresh('config') ?? $plugin;
    }

    public function disable(IntegrationPlugin $plugin): IntegrationPlugin
    {
        $manifest = $this->scanner->requireManifest((string) $plugin->domain, (string) $plugin->slug);

        $plugin->forceFill([
            'status' => IntegrationPlugin::STATUS_DISABLED,
        ])->save();

        $this->syncSelectionSetting($manifest, false);
        $this->forgetDomainRuntime($manifest->domain);

        return $plugin->fresh('config') ?? $plugin;
    }

    private function assertManifestExecutable(PluginManifest $manifest): void
    {
        $this->fileLoader->ensureLoaded($manifest);

        if (! class_exists($manifest->entryClass)) {
            throw new BusinessException('插件入口类不存在', 42200);
        }

        if (! method_exists($manifest->entryClass, 'execute')) {
            throw new BusinessException('插件入口类缺少 execute 方法', 42200);
        }
    }

    private function syncSelectionSetting(PluginManifest $manifest, bool $enabled): void
    {
        $selection = is_array($manifest->extra['selection_setting'] ?? null) ? $manifest->extra['selection_setting'] : [];
        $group = trim((string) ($selection['group'] ?? ''));
        $key = trim((string) ($selection['key'] ?? ''));
        $value = trim((string) ($selection['value'] ?? ''));

        if ($group === '' || $key === '') {
            return;
        }

        if ($enabled) {
            Setting::setValue($group, $key, $value !== '' ? $value : $manifest->key);

            return;
        }

        $current = trim((string) Setting::getValue($group, $key, ''));
        if ($current === ($value !== '' ? $value : $manifest->key)) {
            Setting::setValue($group, $key, '');
        }
    }

    private function forgetDomainRuntime(string $domain): void
    {
        match ($domain) {
            PluginDomain::PAYMENT => $this->forgetInstances(PaymentGatewayRegistry::class, PaymentGatewayManager::class),
            PluginDomain::VERIFICATION => $this->forgetInstances(VerificationDriverManager::class),
            PluginDomain::MAIL => $this->forgetInstances(MailDriverManager::class),
            PluginDomain::SMS => $this->forgetInstances(SmsDriverManager::class),
            PluginDomain::UPSTREAM => $this->forgetInstances(ProviderRegistry::class, ProviderResolver::class),
            default => null,
        };
    }

    private function forgetInstances(string ...$classes): void
    {
        foreach ($classes as $class) {
            $this->container->forgetInstance($class);
        }
    }
}
