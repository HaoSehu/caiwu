<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceUpgradeService;
use App\Services\Finance\InvoiceService;
use App\Services\System\OperationLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceUpgradeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_host_upgrade_invoice_for_user_builds_upgrade_invoice(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'host-upgrade-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Upgrade Supplier '.$suffix,
            'code' => 'upgrade-supplier-'.$suffix,
            'interface_type' => 'mofang_finance_api',
            'api_url' => 'https://upgrade-'.$suffix.'.example.test',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
        ]);

        $product = Product::query()->create([
            'name' => 'Upgrade Product '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '10.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
            'supplier_id' => (int) $supplier->id,
        ]);

        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => 'Upgrade Service '.$suffix,
            'domain' => 'upgrade-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'amount' => '10.00',
            'status' => ServiceStatus::ACTIVE,
            'provision_data' => [
                'upstream_host_id' => 778899,
                'provider' => 'mofang_finance_api',
            ],
            'expires_at' => now()->addMonth(),
            'auto_renew' => 0,
        ]);

        $detailService = $this->createMock(ServiceDetailService::class);
        $detailService->method('findUserService')->willReturn($service);
        $detailService->method('resolveUpstreamContext')->willReturn([
            new class
            {
                public function previewHostUpgrade($supplier, int $hostId, int $productId, string $billingCycle, ?string $jwt = null): array
                {
                    return [
                        'status' => 200,
                        'data' => [
                            'name' => '升级到高配',
                            'amount_total' => '88.80',
                            'billingcycle' => $billingCycle,
                            'promo_code' => 'PROMO88',
                        ],
                    ];
                }
            },
            $supplier,
            778899,
            'jwt-token',
        ]);
        $detailService->method('assertSuccess');
        $detailService->method('extractPayload')->willReturn([
            'name' => '升级到高配',
            'amount_total' => '88.80',
            'billingcycle' => 'monthly',
            'promo_code' => 'PROMO88',
        ]);

        $operationLogService = $this->createMock(OperationLogService::class);
        $operationLogService->expects($this->once())->method('writeServiceConsoleLog');

        $serviceUpgradeService = new ServiceUpgradeService(
            $detailService,
            app(InvoiceService::class),
            $operationLogService,
        );

        $invoice = $serviceUpgradeService->createInvoiceForUser($user, (int) $service->id, [
            'product_id' => 5566,
            'billing_cycle' => 'monthly',
            'promo_code' => 'PROMO88',
        ], [
            'actor_type' => 'client',
            'actor_user_id' => (int) $user->id,
            'actor_name' => (string) $user->email,
            'trace_id' => 'host-upgrade-'.$suffix,
        ]);

        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->status);
        $this->assertSame('88.80', number_format((float) $invoice->amount, 2, '.', ''));
        $this->assertSame('host_upgrade', (string) data_get($invoice->config_pricing_snapshot ?? [], 'meta.kind', ''));
        $this->assertSame(5566, (int) data_get($invoice->config_pricing_snapshot ?? [], 'meta.product_id', 0));
        $this->assertSame('PROMO88', (string) data_get($invoice->config_pricing_snapshot ?? [], 'meta.promo_code', ''));
    }

    public function test_process_paid_upgrade_order_uses_new_upgrade_chain(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'host-upgrade-paid-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $supplier = Supplier::query()->create([
            'name' => 'Upgrade Paid Supplier '.$suffix,
            'code' => 'upgrade-paid-supplier-'.$suffix,
            'interface_type' => 'mofang_finance_api',
            'api_url' => 'https://upgrade-paid-'.$suffix.'.example.test',
            'api_username' => 'demo',
            'api_key' => 'secret',
            'status' => 1,
        ]);

        $product = Product::query()->create([
            'name' => 'Upgrade Paid Product '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '10.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
            'supplier_id' => (int) $supplier->id,
        ]);

        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => 'Upgrade Paid Service '.$suffix,
            'domain' => 'upgrade-paid-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'amount' => '10.00',
            'status' => ServiceStatus::ACTIVE,
            'provision_data' => [
                'upstream_host_id' => 778899,
                'provider' => 'mofang_finance_api',
            ],
            'expires_at' => now()->addMonth(),
            'auto_renew' => 0,
        ]);

        $order = Order::query()->create([
            'order_no' => 'UPG'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'product_spec_snapshot' => '升级订单',
            'product_type_snapshot' => 'vps',
            'service_id' => (int) $service->id,
            'type' => 'upgrade',
            'amount' => '88.80',
            'discount' => '0.00',
            'paid_amount' => '88.80',
            'billing_cycle' => 'monthly',
            'config_snapshot' => ['upgrade_product_id' => 5566],
            'config_pricing_snapshot' => [
                'meta' => [
                    'kind' => 'host_upgrade',
                    'product_id' => 5566,
                    'billing_cycle' => 'monthly',
                    'promo_code' => 'PROMO88',
                ],
            ],
            'coupon_snapshot' => [],
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);

        $detailService = $this->createMock(ServiceDetailService::class);
        $detailService->method('resolveUpstreamContext')->willReturn([
            new class
            {
                public function previewHostUpgrade($supplier, int $hostId, int $productId, string $billingCycle, ?string $jwt = null): array
                {
                    return ['status' => 200, 'data' => []];
                }

                public function applyHostUpgradePromoCode($supplier, int $hostId, string $promoCode, ?string $jwt = null): array
                {
                    return ['status' => 200, 'data' => []];
                }

                public function checkoutHostUpgrade($supplier, int $hostId, ?string $jwt = null): array
                {
                    return ['status' => 200, 'data' => ['invoiceid' => 9988]];
                }

                public function post($supplier, string $uri, array|string $payload = [], ?string $jwt = null, array $headers = [], array $query = []): array
                {
                    return ['status' => 200, 'data' => []];
                }

                public function getHostDetail($supplier, int $hostId, ?string $jwt = null): array
                {
                    return ['status' => 200, 'data' => ['host' => ['domainstatus' => 'Active']]];
                }
            },
            $supplier,
            778899,
            'jwt-token',
        ]);
        $detailService->method('assertSuccess');
        $detailService->method('extractPayload')->willReturnCallback(fn (array $response) => $response['data'] ?? $response);
        $detailService->expects($this->once())->method('syncServiceFromRemote');

        $operationLogService = $this->createMock(OperationLogService::class);
        $operationLogService->expects($this->once())->method('writeServiceConsoleLog');

        $serviceUpgradeService = new ServiceUpgradeService(
            $detailService,
            app(InvoiceService::class),
            $operationLogService,
        );

        $resolved = $serviceUpgradeService->processPaidUpgradeOrder($order);

        $this->assertInstanceOf(Service::class, $resolved);
        $this->assertSame(OrderStatus::COMPLETED, (int) $order->fresh()->status);
        $this->assertSame('host_upgrade', (string) data_get($service->fresh()->provision_data ?? [], 'last_upgrade_kind', ''));
        $this->assertSame(9988, (int) data_get($service->fresh()->provision_data ?? [], 'last_upgrade_invoice_id', 0));
    }
}
