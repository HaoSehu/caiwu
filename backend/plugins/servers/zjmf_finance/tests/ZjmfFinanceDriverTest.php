<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Upstream\Contracts\ProvidesConsoleAccess;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesConsoleNetwork;
use App\Services\Upstream\Contracts\ProvidesConsoleRuntime;
use App\Services\Upstream\Contracts\ProvidesConsoleSecurity;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\Contracts\ProvidesStatusSync;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderKey;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfCloudConfigTemplate;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceAdapter;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/ZjmfProductTypeMapper.php';
require_once __DIR__.'/../lib/ZjmfCloudConfigTemplate.php';
require_once __DIR__.'/../lib/ZjmfAuthManager.php';
require_once __DIR__.'/../lib/ZjmfFinanceTransport.php';
require_once __DIR__.'/../lib/ZjmfCatalogService.php';
require_once __DIR__.'/../lib/ZjmfProvisionService.php';
require_once __DIR__.'/../lib/ZjmfRenewService.php';
require_once __DIR__.'/../lib/ZjmfStatusService.php';
require_once __DIR__.'/../lib/ZjmfConsoleService.php';
require_once __DIR__.'/../lib/ZjmfNetworkService.php';
require_once __DIR__.'/../lib/ZjmfSecurityService.php';
require_once __DIR__.'/../lib/ZjmfFinanceAdapter.php';
require_once __DIR__.'/../lib/ZjmfFinanceDriver.php';

class ZjmfFinanceDriverTest extends TestCase
{
    private function driver(): ZjmfFinanceDriver
    {
        $adapter = new ZjmfFinanceAdapter(
            $this->createMock(HostingPanelApiTransport::class),
            new ZjmfCloudConfigTemplate,
        );

        return new ZjmfFinanceDriver($adapter);
    }

    public function test_driver_key_is_independent_zjmf_finance_api(): void
    {
        $this->assertSame(ProviderKey::ZJMF_FINANCE_API, $this->driver()->key());
        $this->assertNotSame(ProviderKey::HOSTING_PANEL_API, $this->driver()->key());
    }

    #[DataProvider('capabilityProvider')]
    public function test_driver_resolves_each_declared_capability(string $capability): void
    {
        $driver = $this->driver();

        $this->assertTrue($driver->supports($capability));
        $this->assertInstanceOf(ZjmfFinanceAdapter::class, $driver->resolve($capability));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function capabilityProvider(): array
    {
        return [
            'access' => [ProvidesConsoleAccess::class],
            'catalog' => [ProvidesConsoleCatalog::class],
            'network' => [ProvidesConsoleNetwork::class],
            'runtime' => [ProvidesConsoleRuntime::class],
            'security' => [ProvidesConsoleSecurity::class],
            'provisioning' => [ProvidesProvisioning::class],
            'renewal' => [ProvidesRenewal::class],
            'auth refresh' => [ProvidesScheduledAuthRefresh::class],
            'status sync' => [ProvidesStatusSync::class],
        ];
    }

    public function test_driver_rejects_unknown_capability(): void
    {
        $driver = $this->driver();

        $this->assertFalse($driver->supports('App\\Services\\Upstream\\Contracts\\NotACapability'));
        $this->assertNull($driver->resolve('App\\Services\\Upstream\\Contracts\\NotACapability'));
    }

    public function test_driver_supplier_form_schema_is_serializable(): void
    {
        $schema = $this->driver()->supplierFormSchema();

        $this->assertIsArray($schema);
        $this->assertNotSame('', (string) json_encode($schema));
        $this->assertArrayHasKey('fields', $schema);
        $this->assertNotEmpty($schema['fields']);
    }

    public function test_provider_key_constant_not_aliased(): void
    {
        $reflection = new ReflectionClass(ProviderKey::class);

        $this->assertSame('zjmf_finance_api', (string) $reflection->getConstant('ZJMF_FINANCE_API'));
        $this->assertSame('hosting_panel_api', (string) $reflection->getConstant('HOSTING_PANEL_API'));
        $this->assertNotSame(
            (string) $reflection->getConstant('ZJMF_FINANCE_API'),
            (string) $reflection->getConstant('HOSTING_PANEL_API')
        );
    }
}
