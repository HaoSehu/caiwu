<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Integrations\Plugins\IntegrationPluginService;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginProviderRegistry;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Integrations\Plugins\Support\PluginProbeMemo;
use Illuminate\Support\ServiceProvider;

class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginScanner::class);
        $this->app->singleton(PluginFileLoader::class);
        $this->app->singleton(PluginConfigRepository::class);
        $this->app->singleton(PluginProviderRegistry::class);
        $this->app->singleton(PluginRuntimeRegistry::class);
        $this->app->singleton(PluginInstaller::class);
        $this->app->singleton(IntegrationPluginService::class);

        // 探测动作 memo：scoped 生命周期（HTTP 响应后/队列任务前后由框架 flush），
        // PluginRuntimeRegistry 每次调用时经容器 make 取当前作用域实例。
        $this->app->scoped(PluginProbeMemo::class);
    }

    public function boot(PluginProviderRegistry $providers): void
    {
        $providers->bootEnabledProviders();
    }
}
