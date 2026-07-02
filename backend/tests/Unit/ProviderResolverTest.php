<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiDriver;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangCloudConfigTemplate;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangFinanceAdapter;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangFinanceDriver;
use Tests\TestCase;

class ProviderResolverTest extends TestCase
{
    public function test_it_resolves_mofang_supplier_key_to_mofang_driver(): void
    {
        $resolver = $this->makeResolver();
        $supplier = new Supplier([
            'interface_type' => ProviderKey::MOFANG_FINANCE_API,
        ]);

        $resolved = $resolver->resolveForSupplier($supplier);

        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, $resolved->rawKey());
        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, $resolved->key());
        $this->assertSame('魔方财务接口', $resolved->label());
        $this->assertContains(ProvidesProvisioning::class, $resolved->capabilities());
        $this->assertContains(ProvidesConsoleCatalog::class, $resolved->capabilities());
        $this->assertTrue($resolved->supports(ProvidesProvisioning::class));
        $this->assertSame([
            'key' => ProviderKey::MOFANG_FINANCE_API,
            'label' => '魔方财务接口',
            'capabilities' => $resolved->capabilities(),
        ], $resolved->descriptor()->toArray());
    }

    public function test_mofang_capabilities_resolve_to_mofang_adapter_instead_of_shared_transport(): void
    {
        $resolver = $this->makeResolver();
        $supplier = new Supplier([
            'interface_type' => ProviderKey::MOFANG_FINANCE_API,
        ]);

        $catalogCapability = $resolver
            ->resolveForSupplier($supplier)
            ->require(ProvidesConsoleCatalog::class);

        $this->assertInstanceOf(MofangFinanceAdapter::class, $catalogCapability);
        $this->assertNotInstanceOf(HostingPanelApiTransport::class, $catalogCapability);
    }

    public function test_service_level_provider_key_takes_priority_over_product_and_supplier(): void
    {
        $resolver = $this->makeResolver();
        $supplier = new Supplier([
            'interface_type' => ProviderKey::HOSTING_PANEL_API,
        ]);
        $product = new Product([
            'provision_module' => '',
        ]);
        $product->setRelation('supplier', $supplier);

        $service = new Service([
            'provision_data' => [
                'provider' => ProviderKey::MOFANG_FINANCE_API,
            ],
        ]);
        $service->setRelation('product', $product);

        $resolved = $resolver->resolveForService($service);

        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, $resolved->rawKey());
        $this->assertSame(ProviderKey::MOFANG_FINANCE_API, $resolved->key());
        $this->assertTrue($resolved->isResolved());
    }

    public function test_service_with_mismatched_provider_still_uses_stored_provider(): void
    {
        $resolver = $this->makeResolver();
        $supplier = new Supplier([
            'interface_type' => ProviderKey::MOFANG_FINANCE_API,
        ]);
        $product = new Product([
            'provision_module' => ProviderKey::MOFANG_FINANCE_API,
        ]);
        $product->setRelation('supplier', $supplier);

        $service = new Service([
            'provision_data' => [
                'provider' => ProviderKey::HOSTING_PANEL_API,
                'supplier_id' => 1,
            ],
        ]);
        $service->setRelation('product', $product);

        $resolved = $resolver->resolveForService($service);

        $this->assertSame(ProviderKey::HOSTING_PANEL_API, $resolved->key(),
            'ProviderResolver preserves service-level provider even when mismatched — fix requires data correction or write-path fix');
    }

    private function makeResolver(): ProviderResolver
    {
        $transport = $this->createMock(HostingPanelApiTransport::class);

        return new ProviderResolver(new ProviderRegistry([
            new HostingPanelApiDriver($transport),
            new MofangFinanceDriver(new MofangFinanceAdapter($transport, new MofangCloudConfigTemplate)),
        ]));
    }
}
