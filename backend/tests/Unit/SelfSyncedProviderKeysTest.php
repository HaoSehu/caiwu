<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Automation\Heartbeat\Providers\CoreScheduledTaskProvider;
use App\Services\System\SettingService;
use App\Services\Upstream\Contracts\ProvidesSelfStatusSync;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderRegistry;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 全量状态同步的自同步排除集回归：
 * 排除集必须来自 ProviderRegistry 的实时插件能力清单（manifest 文件），
 * 而非 integration_plugins.capabilities_json 落库快照——快照在部署后
 * 不重装插件不会刷新，曾造成定向同步与全量同步同周期双拉取。
 */
class SelfSyncedProviderKeysTest extends TestCase
{
    public function test_only_drivers_declaring_self_status_sync_are_excluded(): void
    {
        $this->bindRegistry([
            $this->makeDriver('zjmf_finance_api', true),
            $this->makeDriver('hosting_panel_api', false),
        ]);

        $this->assertSame(['zjmf_finance_api'], $this->invokeSelfSyncedProviderKeys());
    }

    public function test_blank_registry_keys_are_dropped(): void
    {
        $this->bindRegistry([
            $this->makeDriver('   ', true),
            $this->makeDriver('zjmf_finance_api', true),
        ]);

        $this->assertSame(['zjmf_finance_api'], $this->invokeSelfSyncedProviderKeys());
    }

    public function test_empty_registry_yields_empty_exclusion_set(): void
    {
        $this->bindRegistry([]);

        $this->assertSame([], $this->invokeSelfSyncedProviderKeys());
    }

    /**
     * @param  list<UpstreamDriver>  $drivers
     */
    private function bindRegistry(array $drivers): void
    {
        $this->app->instance(ProviderRegistry::class, new ProviderRegistry($drivers));
    }

    private function invokeSelfSyncedProviderKeys(): array
    {
        $provider = new CoreScheduledTaskProvider(new SettingService);
        $method = new ReflectionMethod($provider, 'selfSyncedProviderKeys');
        $method->setAccessible(true);

        return $method->invoke($provider);
    }

    private function makeDriver(string $key, bool $selfSynced): UpstreamDriver
    {
        return new class($key, $selfSynced) implements UpstreamDriver
        {
            public function __construct(
                private readonly string $driverKey,
                private readonly bool $selfSynced,
            ) {}

            public function key(): string
            {
                return $this->driverKey;
            }

            public function label(): string
            {
                return $this->driverKey;
            }

            public function capabilities(): array
            {
                return $this->selfSynced ? [ProvidesSelfStatusSync::class] : [];
            }

            public function supports(string $capability): bool
            {
                return in_array($capability, $this->capabilities(), true);
            }

            public function resolve(string $capability): ?object
            {
                return null;
            }
        };
    }
}
