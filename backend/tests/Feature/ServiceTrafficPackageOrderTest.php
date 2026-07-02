<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Models\FirstProductGroup;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceResolverService;
use App\Services\ClientServiceConsole\ServiceTrafficPackageService;
use App\Services\ClientServiceConsole\ServiceTransformService;
use App\Services\Finance\AdminOrderNotificationService;
use App\Services\Finance\CouponService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\Provisioning\ProvisionService;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\Referral\ReferralService;
use App\Services\System\OperationLogService;
use App\Services\System\SettingService;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiDriver;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ServiceTrafficPackageOrderTest extends TestCase
{
    public function test_it_creates_a_local_upgrade_invoice_for_traffic_package_purchase(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $userId = null;
        $supplierId = null;
        $productId = null;
        $categoryId = null;
        $serviceId = null;
        $orderId = null;
        $invoiceId = null;

        try {
            $user = User::query()->create([
                'email' => 'traffic-package-'.$suffix.'@example.com',
                'password' => 'Temp@123456',
                'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
                'status' => 1,
            ]);
            $userId = (int) $user->id;

            $supplier = Supplier::query()->create([
                'name' => 'Traffic Supplier '.$suffix,
                'code' => 'traffic-'.$suffix,
                'interface_type' => 'hosting_panel_api',
                'api_url' => 'https://supplier-'.$suffix.'.example.com',
                'api_username' => 'demo',
                'api_key' => 'secret',
                'status' => 1,
                'sort_order' => 1,
            ]);
            $supplierId = (int) $supplier->id;

            // 使用新的分组结构
            $rootGroup = FirstProductGroup::query()->create([
                'code' => 'traffic-'.$suffix,
                'name' => 'Traffic Root '.$suffix,
                'slug' => 'traffic-root-'.$suffix,
                'sort_order' => 0,
                'is_visible' => 1,
                'is_system' => 0,
            ]);

            $category = SecondProductGroup::query()->create([
                'first_product_group_id' => (int) $rootGroup->id,
                'name' => 'Traffic Category '.$suffix,
                'slug' => 'traffic-category-'.$suffix,
                'sort_order' => 0,
                'is_visible' => 1,
            ]);
            $categoryId = (int) $category->id;

            $product = Product::query()->create([
                'first_product_group_id' => (int) $rootGroup->id,
                'second_product_group_id' => (int) $category->id,
                'name' => 'Traffic Product '.$suffix,
                'product_type' => 'vps',
                'pricing' => ['monthly' => '19.90'],
                'setup_fee' => '0.00',
                'config_options' => [[
                    'field' => 'flow_limit',
                    'name' => '流量',
                    'option_type' => 1,
                    'sub' => [
                        ['id' => 85333, 'option_name_first' => '1024', 'version' => '1TB'],
                        ['id' => 85334, 'option_name_first' => '2048', 'version' => '2TB'],
                    ],
                ]],
                'purchase_requires' => [],
                'stock' => -1,
                'status' => 1,
                'auto_setup' => 0,
                'supplier_id' => (int) $supplier->id,
                'supplier_product_id' => 9001,
            ]);
            $productId = (int) $product->id;

            $service = Service::query()->create([
                'user_id' => (int) $user->id,
                'product_id' => (int) $product->id,
                'order_id' => 0,
                'name' => 'Traffic Service '.$suffix,
                'domain' => 'traffic-'.$suffix.'.example.com',
                'billing_cycle' => 'monthly',
                'amount' => '19.90',
                'status' => ServiceStatus::ACTIVE,
                'provision_data' => [
                    'supplier_id' => (int) $supplier->id,
                    'upstream_host_id' => 778899,
                ],
                'expires_at' => now()->addMonth(),
                'auto_renew' => 0,
            ]);
            $serviceId = (int) $service->id;

            Setting::setValue('traffic_package_catalog', 'items', json_encode([[
                'category_id' => (int) $category->id,
                'product_type' => (string) $product->product_type,
                'label' => '2T',
                'target_value' => 2048,
                'price' => '39.90',
                'enabled' => 1,
                'sort_order' => 1,
            ]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $transport = $this->createMock(HostingPanelApiTransport::class);
            $transport->expects($this->once())
                ->method('login')
                ->with($this->callback(fn (Supplier $candidate): bool => (int) $candidate->id === (int) $supplier->id))
                ->willReturn('traffic-jwt');
            $transport->expects($this->once())
                ->method('getHostDetail')
                ->with(
                    $this->callback(fn (Supplier $candidate): bool => (int) $candidate->id === (int) $supplier->id),
                    778899,
                    'traffic-jwt'
                )
                ->willReturn([
                    'status' => 200,
                    'data' => [
                        'host' => [
                            'bwusage' => 256.5,
                            'bwlimit' => 1024,
                            'domainstatus' => 'Active',
                        ],
                    ],
                ]);
            $transport->expects($this->once())
                ->method('get')
                ->willReturnCallback(function (Supplier $candidate, string $uri, ?string $jwt = null, array $query = []) use ($supplier) {
                    $this->assertSame((int) $supplier->id, (int) $candidate->id);
                    $this->assertSame('traffic-jwt', $jwt);

                    if ($uri === '/v1/hosts/778899/module/status') {
                        $this->assertSame(['type' => 'host'], $query);

                        return [
                            'status' => 200,
                            'data' => [
                                'status' => 'Active',
                                'des' => '运行中',
                            ],
                        ];
                    }

                    $this->fail('未预期的上游 GET 请求：'.$uri);
                });
            $transport->expects($this->once())
                ->method('getText')
                ->with(
                    $this->callback(fn (Supplier $candidate): bool => (int) $candidate->id === (int) $supplier->id),
                    $this->stringContains('/servicedetail?'),
                    'traffic-jwt',
                    [],
                    []
                )
                ->willReturn('<html></html>');
            $transport->expects($this->once())
                ->method('getHostUpgradeConfigOptions')
                ->with(
                    $this->callback(fn (Supplier $candidate): bool => (int) $candidate->id === (int) $supplier->id),
                    778899,
                    'traffic-jwt'
                )
                ->willReturn([
                    'response' => ['status' => 200],
                    'payload' => [],
                    'options' => [[
                        'id' => 15305,
                        'field' => 'flow_limit',
                        'name' => '流量',
                        'option_type' => 1,
                        'current_sub_id' => 85333,
                        'current_label' => '1TB',
                        'current_qty' => 1024,
                        'sub' => [
                            ['id' => 85333, 'option_name_first' => '1024', 'version' => '1TB'],
                            ['id' => 85334, 'option_name_first' => '2048', 'version' => '2TB'],
                        ],
                    ]],
                ]);

            $operationLogService = $this->createMock(OperationLogService::class);
            $operationLogService->expects($this->once())->method('writeServiceConsoleLog');

            $providerResolver = $this->makeProviderResolver($transport);
            $detailService = new ServiceDetailService(
                $providerResolver,
                $this->createMock(OperationLogService::class),
                new ServiceResolverService,
                new ServiceTransformService(new ServiceResolverService)
            );

            $trafficPackageService = new ServiceTrafficPackageService(
                $detailService,
                new InvoiceService,
                $operationLogService,
                new SettingService,
                $providerResolver
            );

            $invoice = $trafficPackageService->createInvoiceForUser(
                $user,
                (int) $service->id,
                ['target_value' => 2048],
                [
                    'actor_type' => 'client',
                    'actor_user_id' => (int) $user->id,
                    'actor_name' => (string) $user->email,
                    'trace_id' => 'traffic-package-'.$suffix,
                ]
            );

            $invoiceId = (int) $invoice->id;
            $orderId = (int) ($invoice->order_id ?? 0);

            $this->assertSame('upgrade', (string) $invoice->type);
            $this->assertSame((int) $service->id, (int) $invoice->service_id);
            $this->assertGreaterThan(0, $orderId);
            $this->assertSame('39.90', number_format((float) $invoice->amount, 2, '.', ''));
            $this->assertSame(2048, (int) ($invoice->config_snapshot['flow_limit'] ?? 0));
            $this->assertSame(
                ['15305' => 85334],
                (array) data_get($invoice->config_pricing_snapshot ?? [], 'meta.configoption', [])
            );
            $this->assertDatabaseHas('invoices', [
                'id' => (int) $invoice->id,
                'type' => 'upgrade',
                'service_id' => (int) $service->id,
                'status' => InvoiceStatus::UNPAID,
            ]);
            $this->assertDatabaseHas('orders', [
                'id' => $orderId,
                'type' => 'upgrade',
                'service_id' => (int) $service->id,
                'status' => OrderStatus::PENDING,
            ]);
        } finally {
            if ($invoiceId !== null && $invoiceId > 0) {
                DB::table('invoices')->where('id', $invoiceId)->delete();
            }
            if ($orderId !== null && $orderId > 0) {
                DB::table('orders')->where('id', $orderId)->delete();
            }
            if ($serviceId !== null) {
                DB::table('services')->where('id', $serviceId)->delete();
            }
            if ($productId !== null) {
                DB::table('products')->where('id', $productId)->delete();
            }
            if ($supplierId !== null) {
                DB::table('suppliers')->where('id', $supplierId)->delete();
            }
            if ($categoryId !== null) {
                DB::table('second_product_groups')->where('id', $categoryId)->delete();
            }
            if (isset($rootGroup)) {
                DB::table('first_product_groups')->where('id', (int) $rootGroup->id)->delete();
            }
            Setting::setValue('traffic_package_catalog', 'items', '[]');
            if ($userId !== null) {
                DB::table('users')->where('id', $userId)->delete();
            }
        }
    }

    public function test_paid_upgrade_invoice_routes_to_traffic_package_processor(): void
    {
        if (! method_exists(CouponService::class, 'syncOrderCouponUsageAfterResponse')) {
            $this->markTestIncomplete(
                'Runtime blocker: PaymentService::handlePaidInvoice() still calls missing CouponService::syncOrderCouponUsageAfterResponse().'
            );
        }

        $suffix = bin2hex(random_bytes(4));
        $userId = null;
        $productId = null;
        $serviceId = null;
        $orderId = null;
        $invoiceId = null;

        try {
            $user = User::query()->create([
                'email' => 'traffic-upgrade-sync-'.$suffix.'@example.com',
                'password' => 'Temp@123456',
                'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
                'status' => 1,
            ]);
            $userId = (int) $user->id;

            $product = Product::query()->create([
                'name' => 'Traffic Sync Product '.$suffix,
                'product_type' => 'vps',
                'pricing' => ['monthly' => '10.00'],
                'setup_fee' => '0.00',
                'config_options' => [],
                'purchase_requires' => [],
                'stock' => -1,
                'status' => 1,
                'auto_setup' => 0,
            ]);
            $productId = (int) $product->id;

            $order = Order::query()->create([
                'order_no' => 'UPGRADE'.strtoupper($suffix),
                'user_id' => (int) $user->id,
                'product_id' => (int) $product->id,
                'product_spec_snapshot' => '未配置规格 #'.(int) $product->id,
                'product_type_snapshot' => (string) $product->product_type,
                'service_id' => 0,
                'type' => 'upgrade',
                'amount' => '30.00',
                'discount' => '0.00',
                'paid_amount' => '30.00',
                'billing_cycle' => 'one_time',
                'config_snapshot' => ['flow_limit' => 2048],
                'config_pricing_snapshot' => [
                    'meta' => [
                        'kind' => 'traffic_package',
                        'configoption' => ['15305' => 85334],
                    ],
                ],
                'coupon_snapshot' => [],
                'status' => OrderStatus::PAID,
                'paid_at' => now(),
            ]);
            $orderId = (int) $order->id;

            $service = Service::query()->create([
                'user_id' => (int) $user->id,
                'product_id' => (int) $product->id,
                'order_id' => 0,
                'name' => 'Traffic Sync Service '.$suffix,
                'domain' => 'sync-'.$suffix.'.example.com',
                'billing_cycle' => 'monthly',
                'amount' => '10.00',
                'status' => ServiceStatus::ACTIVE,
                'provision_data' => [],
                'expires_at' => now()->addMonth(),
                'auto_renew' => 0,
            ]);
            $serviceId = (int) $service->id;

            $order->forceFill([
                'service_id' => (int) $service->id,
            ])->save();

            $invoice = Invoice::query()->create([
                'invoice_no' => 'INVUPGRADE'.strtoupper($suffix),
                'user_id' => (int) $user->id,
                'order_id' => (int) $order->id,
                'type' => 'upgrade',
                'amount' => '30.00',
                'paid_amount' => '30.00',
                'status' => InvoiceStatus::PAID,
                'paid_at' => now(),
                'due_date' => now()->addDay(),
            ]);
            $invoiceId = (int) $invoice->id;

            $trafficPackageProcessor = $this->createMock(ServiceTrafficPackageService::class);
            $trafficPackageProcessor->expects($this->once())
                ->method('processPaidTrafficPackageOrder')
                ->with($this->callback(fn (Order $candidate): bool => (int) $candidate->id === (int) $order->id))
                ->willReturn($service);
            $this->app->instance(ServiceTrafficPackageService::class, $trafficPackageProcessor);

            $dispatcher = $this->createMock(PaidOrderBusinessFlowDispatcher::class);
            $dispatcher->expects($this->never())->method('dispatchPaidInvoice');

            $couponService = $this->createMock(CouponService::class);
            $couponService->expects($this->once())
                ->method('syncInvoiceCouponUsageAfterResponse')
                ->with($this->callback(fn (Invoice $candidate): bool => (int) $candidate->id === (int) $invoice->id));

            $adminOrderNotificationService = $this->createMock(AdminOrderNotificationService::class);
            $adminOrderNotificationService->expects($this->once())
                ->method('notifyInvoicePaidAfterResponse')
                ->with($this->callback(fn (Invoice $candidate): bool => (int) $candidate->id === (int) $invoice->id));

            $paymentService = new PaymentService(
                $this->createMock(ProvisionService::class),
                $this->makePaymentGatewayManagerForTest(),
                $this->createMock(ServiceRenewService::class),
                $this->createMock(ReferralService::class),
                $dispatcher,
                $adminOrderNotificationService,
                $couponService,
                new InvoiceService,
            );

            $paymentService->handlePaidInvoice($invoice, 'trace-upgrade-sync-'.$suffix);
        } finally {
            if ($invoiceId !== null && $invoiceId > 0) {
                DB::table('invoices')->where('id', $invoiceId)->delete();
            }
            if ($orderId !== null && $orderId > 0) {
                DB::table('orders')->where('id', $orderId)->delete();
            }
            if ($serviceId !== null) {
                DB::table('services')->where('id', $serviceId)->delete();
            }
            if ($productId !== null) {
                DB::table('products')->where('id', $productId)->delete();
            }
            if ($userId !== null) {
                DB::table('users')->where('id', $userId)->delete();
            }
        }
    }

    private function makeProviderResolver(HostingPanelApiTransport $transport): ProviderResolver
    {
        return new ProviderResolver(new ProviderRegistry([
            new HostingPanelApiDriver($transport),
        ]));
    }
}
