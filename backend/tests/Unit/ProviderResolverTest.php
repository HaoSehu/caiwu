<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiDriver;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Tests\TestCase;

class ProviderResolverTest extends TestCase
{
    public function test_it_normalizes_legacy_supplier_key_to_hosting_panel_api(): void
    {
        $resolver = $this->makeResolver();
        $supplier = new Supplier([
            'interface_type' => 'mofang_finance_api',
        ]);

        $resolved = $resolver->resolveForSupplier($supplier);

        $this->assertSame('mofang_finance_api', $resolved->rawKey());
        $this->assertSame(ProviderKey::HOSTING_PANEL_API, $resolved->key());
        $this->assertTrue($resolved->supports(ProvidesProvisioning::class));
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
                'provider' => 'mofang_finance_api',
            ],
        ]);
        $service->setRelation('product', $product);

        $resolved = $resolver->resolveForService($service);

        $this->assertSame('mofang_finance_api', $resolved->rawKey());
        $this->assertSame(ProviderKey::HOSTING_PANEL_API, $resolved->key());
        $this->assertTrue($resolved->isResolved());
    }

    private function makeResolver(): ProviderResolver
    {
        $transport = $this->createMock(HostingPanelApiTransport::class);

        return new ProviderResolver(new ProviderRegistry([
            new HostingPanelApiDriver($transport),
        ]));
    }
}
