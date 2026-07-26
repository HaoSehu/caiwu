<?php

declare(strict_types=1);

namespace App\Services\Integrations\Plugins;

use App\Exceptions\BusinessException;
use App\Models\IntegrationPlugin;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class PluginProviderRegistry
{
    /**
     * @var array<string, bool>
     */
    private array $bootedProviders = [];

    public function __construct(
        private readonly Container $container,
        private readonly PluginScanner $scanner,
        private readonly PluginFileLoader $fileLoader,
    ) {}

    public function bootEnabledProviders(): void
    {
        try {
            if (! Schema::hasTable('integration_plugins')) {
                return;
            }

            // 只有声明了 provider 的插件需要在框架 boot 阶段读清单并注册。
            // 不加这个过滤，每个请求都会为每个启用插件做一次 realpath + require config.php，
            // 而绝大多数插件根本没有 provider。
            $plugins = IntegrationPlugin::query()
                ->where('status', IntegrationPlugin::STATUS_ENABLED)
                ->whereNotNull('provider_class')
                ->where('provider_class', '<>', '')
                ->get(['domain', 'slug']);

            if ($plugins->isEmpty()) {
                return;
            }
        } catch (\Throwable $exception) {
            Log::warning('[plugins] enabled provider scan skipped', [
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        $plugins->each(function (IntegrationPlugin $plugin): void {
            try {
                $this->activate($this->scanner->requireManifest((string) $plugin->domain, (string) $plugin->slug));
            } catch (\Throwable $exception) {
                Log::error('[plugins] provider boot failed', [
                    'domain' => $plugin->domain,
                    'slug' => $plugin->slug,
                    'message' => $exception->getMessage(),
                ]);
            }
        });
    }

    public function activate(PluginManifest $manifest): void
    {
        $providerClass = $manifest->providerClass;
        if ($providerClass === null || isset($this->bootedProviders[$providerClass])) {
            return;
        }

        $this->fileLoader->ensureLoaded($manifest);

        if (! is_subclass_of($providerClass, ServiceProvider::class)) {
            throw new BusinessException('插件服务提供者必须继承 Laravel ServiceProvider', 42200);
        }

        /** @var ServiceProvider $provider */
        $provider = new $providerClass($this->container);
        $provider->register();
        $this->container->call([$provider, 'boot']);

        $this->bootedProviders[$providerClass] = true;
    }
}
