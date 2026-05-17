<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductCatalog\ProductSyncService;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiDriver;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Tests\TestCase;

class ProductSyncServiceProvisionTest extends TestCase
{
    public function test_assert_product_can_be_provisioned_blocks_when_local_stock_is_insufficient(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $product = Product::query()->create([
            'name' => 'Provision Local Stock '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '99.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => 1,
            'status' => 1,
            'auto_setup' => 0,
        ]);

        $service = new ProductSyncService(
            $this->makeProviderResolver($this->createMock(HostingPanelApiTransport::class))
        );

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('该商品库存不足，无法继续下单');

        $service->assertProductCanBeProvisioned($product, 2);
    }

    public function test_assert_product_can_be_provisioned_counts_reserved_order_quantity_for_upstream_products(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = User::query()->create([
            'email' => 'provision-stock-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Provision Supplier '.$suffix,
            'code' => 'provision-'.$suffix,
            'interface_type' => 'hosting_panel_api',
            'api_url' => 'https://supplier-'.$suffix.'.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
            'sort_order' => 1,
        ]);

        $supplierProductId = random_int(10000, 99999);
        $product = Product::query()->create([
            'name' => 'Provision Remote Stock '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '99.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
            'supplier_id' => (int) $supplier->id,
            'supplier_product_id' => $supplierProductId,
        ]);

        Order::query()->create([
            'order_no' => 'ORDPROVISION'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'product_spec_snapshot' => '未配置规格 #'.(int) $product->id,
            'product_type_snapshot' => (string) $product->product_type,
            'type' => 'new',
            'amount' => '99.00',
            'discount' => '0.00',
            'billing_cycle' => 'monthly',
            'quantity' => 2,
            'config_snapshot' => [],
            'config_pricing_snapshot' => [],
            'status' => OrderStatus::PENDING,
            'service_id' => null,
        ]);

        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->expects($this->once())
            ->method('fetchBatchProductStocks')
            ->with(
                $this->callback(fn (Supplier $candidate): bool => (int) $candidate->id === (int) $supplier->id),
                [$supplierProductId]
            )
            ->willReturn([
                $supplierProductId => ['stock' => 3],
            ]);

        $service = new ProductSyncService($this->makeProviderResolver($transport));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('该商品库存不足，无法继续下单');

        $service->assertProductCanBeProvisioned($product, 2);
    }

    public function test_assert_product_can_be_provisioned_keeps_inventory_reserved_when_order_service_is_still_pending(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $supplier = Supplier::query()->create([
            'name' => 'Provision Supplier Pending Service '.$suffix,
            'code' => 'provision-pending-'.$suffix,
            'interface_type' => 'hosting_panel_api',
            'api_url' => 'https://supplier-'.$suffix.'.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
            'sort_order' => 1,
        ]);

        $supplierProductId = random_int(10000, 99999);
        $product = Product::query()->create([
            'name' => 'Provision Pending Service Stock '.$suffix,
            'product_type' => 'server',
            'pricing' => ['monthly' => '99.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 1,
            'supplier_id' => (int) $supplier->id,
            'supplier_product_id' => $supplierProductId,
        ]);

        $user = User::query()->create([
            'email' => 'provision-pending-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $order = Order::query()->create([
            'order_no' => 'ORDPROVPEND'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'product_spec_snapshot' => '未配置规格 #'.(int) $product->id,
            'product_type_snapshot' => (string) $product->product_type,
            'type' => 'new',
            'amount' => '99.00',
            'discount' => '0.00',
            'billing_cycle' => 'monthly',
            'quantity' => 2,
            'config_snapshot' => [],
            'config_pricing_snapshot' => [],
            'status' => OrderStatus::PROCESSING,
            'service_id' => null,
        ]);

        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'order_id' => (int) $order->id,
            'name' => 'Pending Service '.$suffix,
            'domain' => 'pending-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'amount' => '99.00',
            'status' => ServiceStatus::PENDING,
            'provision_data' => [
                'provision_error' => '上游开通处理中',
            ],
            'auto_renew' => 1,
        ]);

        $order->forceFill([
            'service_id' => (int) $service->id,
        ])->save();

        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->expects($this->once())
            ->method('fetchBatchProductStocks')
            ->with(
                $this->callback(fn (Supplier $candidate): bool => (int) $candidate->id === (int) $supplier->id),
                [$supplierProductId]
            )
            ->willReturn([
                $supplierProductId => ['stock' => 3],
            ]);

        $service = new ProductSyncService($this->makeProviderResolver($transport));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('该商品库存不足，无法继续下单');

        $service->assertProductCanBeProvisioned($product, 2);
    }

    private function makeProviderResolver(HostingPanelApiTransport $transport): ProviderResolver
    {
        return new ProviderResolver(new ProviderRegistry([
            new HostingPanelApiDriver($transport),
        ]));
    }
}
