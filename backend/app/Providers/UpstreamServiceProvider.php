<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiDriver;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Support\ServiceProvider;

class UpstreamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HostingPanelApiTransport::class);
        $this->app->singleton(HostingPanelApiDriver::class);

        $this->app->singleton(ProviderRegistry::class, function ($app): ProviderRegistry {
            return new ProviderRegistry([
                $app->make(HostingPanelApiDriver::class),
            ]);
        });

        $this->app->singleton(ProviderResolver::class);
    }
}
