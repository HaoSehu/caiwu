<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Services\ProductCatalog\ProductSyncService;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiDriver;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Tests\TestCase;

class SupplierBatchConnectRegressionTest extends TestCase
{
    public function test_bulk_connect_supplier_products_creates_local_products_from_upstream_catalog(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $supplier = Supplier::query()->create([
            'name' => 'Batch Connect Supplier '.$suffix,
            'code' => 'batch-connect-'.$suffix,
            'interface_type' => 'hosting_panel_api',
            'api_url' => 'https://supplier-'.$suffix.'.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
            'sort_order' => 1,
        ]);

        $rootCategory = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'vps',
            'name' => 'Imported Root '.$suffix,
            'slug' => 'imported-root-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $supplierProductId = random_int(10000, 99999);
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->expects($this->once())
            ->method('getProductCatalog')
            ->with($this->callback(fn (Supplier $candidate): bool => (int) $candidate->id === (int) $supplier->id))
            ->willReturn([
                'groups' => [],
                'products' => [[
                    'id' => $supplierProductId,
                    'name' => '香港 CN2 2C4G '.$suffix,
                    'group_label' => '香港云服务器 / CN2',
                    'group_name' => 'CN2',
                    'first_group_name' => '香港云服务器',
                    'billingcycle' => 'monthly',
                    'product_price' => '99.00',
                    'monthly_price' => '99.00',
                    'setup_fee' => '10.00',
                    'stock' => 8,
                ]],
            ]);

        $service = new ProductSyncService($this->makeProviderResolver($transport));

        $result = $service->bulkConnectSupplierProducts($supplier, [
            'product_type' => 'vps',
            'root_category_id' => (int) $rootCategory->id,
            'product_ids' => [$supplierProductId],
            'default_status' => 1,
            'default_auto_setup' => 1,
            'sync_config_options' => 0,
        ]);

        $this->assertSame(1, (int) ($result['created_count'] ?? 0));
        $this->assertSame(0, (int) ($result['updated_count'] ?? 0));
        $this->assertSame(0, (int) ($result['skipped_count'] ?? 0));
        $this->assertCount(1, $result['imported_products'] ?? []);

        /** @var Product|null $product */
        $product = Product::query()
            ->with('categoryMapping.parent')
            ->where('supplier_id', (int) $supplier->id)
            ->where('supplier_product_id', $supplierProductId)
            ->first();

        $this->assertNotNull($product);
        $this->assertSame('99.00', $product->pricing['monthly'] ?? null);
        $this->assertSame(1, (int) $product->status);
        $this->assertSame(1, (int) $product->auto_setup);
        $this->assertSame('2', (string) (($product->purchase_requires['upstream_default_config'] ?? [])['cpu'] ?? ''));
        $this->assertSame('4', (string) (($product->purchase_requires['upstream_default_config'] ?? [])['memory'] ?? ''));
        $this->assertNotNull($product->categoryMapping);
        $this->assertNotNull($product->categoryMapping?->parent);
        $this->assertSame((int) $rootCategory->id, (int) $product->categoryMapping?->parent?->id);
        $this->assertSame('香港云服务器 / CN2', $product->categoryMapping?->name);

        $display = (new ProductDisplayNameResolver)->resolveForProduct($product);
        $this->assertSame('2 vCPU 4G', $display['product_display_name'] ?? null);
        $this->assertSame('2 vCPU 4G', $display['product_spec_display'] ?? null);
        $this->assertSame('2 vCPU 4G', $display['cpu_memory_display'] ?? null);
    }

    public function test_display_name_resolver_reads_cpu_and_memory_from_default_config_items(): void
    {
        $product = new Product([
            'id' => 999001,
            'name' => '通用共享',
            'product_type' => 'vps',
            'purchase_requires' => [],
            'config_options' => [
                [
                    'field' => 'cpu',
                    'name' => 'CPU',
                    'default_value' => '-',
                    'sub' => [
                        [
                            'id' => 1,
                            'option_name_first' => '4',
                            'version' => '4核',
                            'hidden' => 0,
                        ],
                    ],
                ],
                [
                    'field' => 'memory',
                    'name' => '内存',
                    'default_value' => '-',
                    'sub' => [
                        [
                            'id' => 2,
                            'option_name_first' => '4096',
                            'version' => '4G',
                            'hidden' => 0,
                        ],
                    ],
                ],
            ],
        ]);

        $display = (new ProductDisplayNameResolver)->resolveForProduct($product);

        $this->assertSame('4 vCPU 4G', $display['product_display_name'] ?? null);
        $this->assertSame('4 vCPU 4G', $display['product_spec_display'] ?? null);
        $this->assertSame('4 vCPU 4G', $display['cpu_memory_display'] ?? null);
    }

    private function makeProviderResolver(HostingPanelApiTransport $transport): ProviderResolver
    {
        return new ProviderResolver(new ProviderRegistry([
            new HostingPanelApiDriver($transport),
        ]));
    }
}
