<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\ServiceInstance;
use App\Models\User;
use App\Services\Finance\CouponService;
use App\Services\Finance\InvoiceService;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\System\NotificationService;
use App\Services\System\OperationLogService;
use App\Services\System\SettingService;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiDriver;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceRenewInvoiceOnlyIdempotencyTest extends TestCase
{
    private function mirrorProductToIdc(Product $product, string $suffix): void
    {
        DB::connection('idc')->table('products')->updateOrInsert(
            ['id' => (int) $product->id],
            Product::buildIdcMirrorPayload($product, 'renew-idempotency-'.$suffix.'-'.(int) $product->id)
        );
    }

    private function mirrorUserToIdc(User $user, string $suffix): void
    {
        $payload = [
            'email' => $user->email,
            'phone' => $user->phone,
            'password' => Hash::make('Temp@123456'),
            'status' => 1,
            'referral_code' => 'R'.strtoupper(substr(md5($suffix.'-'.$user->id), 0, 8)),
            'referrer_user_id' => null,
            'member_level_id' => null,
            'login_email_alert' => 1,
            'login_notify' => 1,
            'login_location_alert' => 1,
            'password_change_alert' => 1,
            'phone_change_alert' => 1,
            'email_change_alert' => 1,
            'marketing_alert' => 0,
            'is_verified' => 0,
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'referred_at' => null,
            'verified_at' => null,
            'last_login_ip' => null,
            'last_login_at' => null,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('idc')->table('users')->updateOrInsert(['id' => (int) $user->id], $payload);
        DB::connection('idc')->table('users')->updateOrInsert(['id' => (int) $user->id], $payload);
    }

    #[Test]
    public function process_paid_renew_invoice_skips_duplicate_completion_for_same_invoice(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'renew-idempotency-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Renew Idempotency',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
        $this->mirrorUserToIdc($user, 'renew-idempotency-'.$suffix);

        $product = Product::query()->create([
            'name' => 'Renew Idempotency Product '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '30.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
        ]);
        $this->mirrorProductToIdc($product, $suffix);

        $originalExpiresAt = Carbon::parse('2026-06-01 00:00:00');

        $service = ServiceInstance::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'source_invoice_id' => null,
            'name' => 'Invoice Only Renew Service '.$suffix,
            'service_no' => 'SVCRENEW'.$suffix,
            'instance_identifier' => 'renew-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'renewal_price' => '30.00',
            'status' => ServiceStatus::ACTIVE,
            'pricing_snapshot_json' => ['monthly' => '30.00'],
            'provision_snapshot_json' => [],
            'expires_at' => $originalExpiresAt,
            'auto_renew' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INVRENEWDONE'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_id' => (int) $service->id,
            'type' => 'renew',
            'amount' => '30.00',
            'paid_amount' => '30.00',
            'billing_cycle' => 'monthly',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'due_date' => now()->addDay(),
        ]);

        $service->forceFill([
            'provision_snapshot_json' => [
                'last_renew_invoice_id' => (int) $invoice->id,
                'last_renew_invoice_no' => (string) $invoice->invoice_no,
                'last_renewed_at' => '2026-05-01 00:00:00',
                'last_renew_billing_cycle' => 'monthly',
                'last_renew_source' => 'local_invoice',
            ],
        ])->save();

        $renewService = new ServiceRenewService(
            new InvoiceService,
            new ProviderResolver(
                new ProviderRegistry([
                    new HostingPanelApiDriver($this->createMock(HostingPanelApiTransport::class)),
                ])
            ),
            $this->createMock(CouponService::class),
            $this->createMock(OperationLogService::class),
            new class extends SettingService
            {
                public function getAutomationConfig(): array
                {
                    return array_merge(parent::defaultAutomationConfig(), [
                        'expire_unsuspend_notify_enabled' => false,
                    ]);
                }
            },
            $this->createMock(NotificationService::class),
        );

        $result = $renewService->processPaidRenewInvoice($invoice);

        $this->assertNotNull($result);
        $this->assertSame((int) $service->id, (int) $result->id);
        $this->assertSame(
            $originalExpiresAt->format('Y-m-d H:i:s'),
            optional($result->expires_at)->format('Y-m-d H:i:s')
        );
        $this->assertSame((int) $invoice->id, (int) (($result->provision_data ?? [])['last_renew_invoice_id'] ?? 0));
        $this->assertSame(
            $originalExpiresAt->format('Y-m-d H:i:s'),
            optional($service->fresh()?->expires_at)->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function process_paid_renew_invoice_sets_service_invoice_id_after_local_success(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'renew-bind-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Renew Bind',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
        $this->mirrorUserToIdc($user, 'renew-bind-'.$suffix);

        $product = Product::query()->create([
            'name' => 'Renew Bind Product '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '45.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
        ]);
        $this->mirrorProductToIdc($product, $suffix);

        $service = ServiceInstance::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'source_invoice_id' => null,
            'name' => 'Renew Bind Service '.$suffix,
            'service_no' => 'SVCRENEW'.$suffix,
            'instance_identifier' => 'bind-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'renewal_price' => '45.00',
            'status' => ServiceStatus::ACTIVE,
            'pricing_snapshot_json' => ['monthly' => '45.00'],
            'provision_snapshot_json' => [],
            'expires_at' => Carbon::parse('2026-06-15 00:00:00'),
            'auto_renew' => 0,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INVRENEWBIND'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_id' => (int) $service->id,
            'type' => 'renew',
            'amount' => '45.00',
            'paid_amount' => '45.00',
            'billing_cycle' => 'monthly',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'due_date' => now()->addDay(),
        ]);

        DB::connection('idc')->table('invoices')->updateOrInsert(
            ['id' => (int) $invoice->id],
            [
                'user_id' => (int) $user->id,
                'order_id' => null,
                'service_id' => (int) $service->id,
                'type' => 'renew',
                'amount' => '45.00',
                'paid_amount' => '45.00',
                'status' => InvoiceStatus::PAID,
                'billing_cycle' => 'monthly',
                'invoice_no' => (string) $invoice->invoice_no,
                'due_date' => now()->addDay(),
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $renewService = new ServiceRenewService(
            new InvoiceService,
            new ProviderResolver(
                new ProviderRegistry([
                    new HostingPanelApiDriver($this->createMock(HostingPanelApiTransport::class)),
                ])
            ),
            $this->createMock(CouponService::class),
            $this->createMock(OperationLogService::class),
            new class extends SettingService
            {
                public function getAutomationConfig(): array
                {
                    return array_merge(parent::defaultAutomationConfig(), [
                        'expire_unsuspend_notify_enabled' => false,
                    ]);
                }
            },
            $this->createMock(NotificationService::class),
        );

        $result = $renewService->processPaidRenewInvoice($invoice);

        $this->assertNotNull($result);
        $this->assertSame((int) $invoice->id, (int) ($result->invoice_id ?? 0));
        $this->assertSame((int) $invoice->id, (int) ($service->fresh()?->invoice_id ?? 0));
        $this->assertSame((int) $invoice->id, (int) (($result->provision_data ?? [])['last_renew_invoice_id'] ?? 0));
    }

    #[Test]
    public function process_paid_renew_invoice_ignores_real_legacy_order_binding_and_keeps_invoice_first_context(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'renew-real-order-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Renew Real Order',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
        $this->mirrorUserToIdc($user, 'renew-real-order-'.$suffix);

        $product = Product::query()->create([
            'name' => 'Renew Real Order Product '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '52.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
        ]);
        $this->mirrorProductToIdc($product, $suffix);

        $service = ServiceInstance::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'source_invoice_id' => null,
            'name' => 'Renew Real Order Service '.$suffix,
            'service_no' => 'SVCRENEW'.$suffix,
            'instance_identifier' => 'renew-real-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'renewal_price' => '52.00',
            'status' => ServiceStatus::ACTIVE,
            'pricing_snapshot_json' => ['monthly' => '52.00'],
            'provision_snapshot_json' => [],
            'expires_at' => Carbon::parse('2026-06-20 00:00:00'),
            'auto_renew' => 0,
        ]);

        $legacyOrder = Order::query()->create([
            'order_no' => 'ORDRENEWREAL'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_id' => (int) $service->id,
            'type' => 'renew',
            'amount' => '52.00',
            'discount' => '0.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PENDING,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INVRENEWREAL'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_id' => (int) $service->id,
            'order_id' => (int) $legacyOrder->id,
            'type' => 'renew',
            'amount' => '52.00',
            'paid_amount' => '52.00',
            'billing_cycle' => 'monthly',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'product_snapshot_json' => [
                'order_id' => (int) $legacyOrder->id,
                'order_no' => (string) $legacyOrder->order_no,
            ],
            'due_date' => now()->addDay(),
        ]);

        DB::connection('idc')->table('invoices')->updateOrInsert(
            ['id' => (int) $invoice->id],
            [
                'user_id' => (int) $user->id,
                'order_id' => (int) $legacyOrder->id,
                'service_id' => (int) $service->id,
                'type' => 'renew',
                'amount' => '52.00',
                'paid_amount' => '52.00',
                'status' => InvoiceStatus::PAID,
                'billing_cycle' => 'monthly',
                'invoice_no' => (string) $invoice->invoice_no,
                'due_date' => now()->addDay(),
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $renewService = new ServiceRenewService(
            new InvoiceService,
            new ProviderResolver(
                new ProviderRegistry([
                    new HostingPanelApiDriver($this->createMock(HostingPanelApiTransport::class)),
                ])
            ),
            $this->createMock(CouponService::class),
            $this->createMock(OperationLogService::class),
            new class extends SettingService
            {
                public function getAutomationConfig(): array
                {
                    return array_merge(parent::defaultAutomationConfig(), [
                        'expire_unsuspend_notify_enabled' => false,
                    ]);
                }
            },
            $this->createMock(NotificationService::class),
        );

        $result = $renewService->processPaidRenewInvoice($invoice);

        $this->assertNotNull($result);
        $this->assertSame((int) $invoice->id, (int) ($result->invoice_id ?? 0));
        $this->assertSame(0, (int) ($result->order_id ?? 0));
        $this->assertSame((int) $invoice->id, (int) (($result->provision_data ?? [])['last_renew_invoice_id'] ?? 0));
        $this->assertSame('local_invoice', (string) (($result->provision_data ?? [])['last_renew_source'] ?? ''));

        $storedOrder = $legacyOrder->fresh();
        $this->assertSame(OrderStatus::PENDING, (int) ($storedOrder?->status ?? -1));
        $this->assertSame('0.00', number_format((float) ($storedOrder?->paid_amount ?? 0), 2, '.', ''));
    }

    #[Test]
    public function process_paid_renew_order_delegates_to_bound_invoice_and_keeps_legacy_order_pending(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'renew-order-delegate-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Renew Order Delegate',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
        $this->mirrorUserToIdc($user, 'renew-order-delegate-'.$suffix);

        $product = Product::query()->create([
            'name' => 'Renew Order Delegate Product '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '61.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
        ]);
        $this->mirrorProductToIdc($product, $suffix);

        $service = ServiceInstance::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'source_invoice_id' => null,
            'name' => 'Renew Order Delegate Service '.$suffix,
            'service_no' => 'SVCRENEW'.$suffix,
            'instance_identifier' => 'renew-order-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'renewal_price' => '61.00',
            'status' => ServiceStatus::ACTIVE,
            'pricing_snapshot_json' => ['monthly' => '61.00'],
            'provision_snapshot_json' => [],
            'expires_at' => Carbon::parse('2026-06-25 00:00:00'),
            'auto_renew' => 0,
        ]);

        $legacyOrder = Order::query()->create([
            'order_no' => 'ORDRENEWDELEGATE'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_id' => (int) $service->id,
            'type' => 'renew',
            'amount' => '61.00',
            'discount' => '0.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PENDING,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INVRENEWDELEGATE'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_id' => (int) $service->id,
            'order_id' => (int) $legacyOrder->id,
            'type' => 'renew',
            'amount' => '61.00',
            'paid_amount' => '61.00',
            'billing_cycle' => 'monthly',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'product_snapshot_json' => [
                'order_id' => (int) $legacyOrder->id,
                'order_no' => (string) $legacyOrder->order_no,
            ],
            'due_date' => now()->addDay(),
        ]);

        DB::connection('idc')->table('invoices')->updateOrInsert(
            ['id' => (int) $invoice->id],
            [
                'user_id' => (int) $user->id,
                'order_id' => (int) $legacyOrder->id,
                'service_id' => (int) $service->id,
                'type' => 'renew',
                'amount' => '61.00',
                'paid_amount' => '61.00',
                'status' => InvoiceStatus::PAID,
                'billing_cycle' => 'monthly',
                'invoice_no' => (string) $invoice->invoice_no,
                'due_date' => now()->addDay(),
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $renewService = new ServiceRenewService(
            new InvoiceService,
            new ProviderResolver(
                new ProviderRegistry([
                    new HostingPanelApiDriver($this->createMock(HostingPanelApiTransport::class)),
                ])
            ),
            $this->createMock(CouponService::class),
            $this->createMock(OperationLogService::class),
            new class extends SettingService
            {
                public function getAutomationConfig(): array
                {
                    return array_merge(parent::defaultAutomationConfig(), [
                        'expire_unsuspend_notify_enabled' => false,
                    ]);
                }
            },
            $this->createMock(NotificationService::class),
        );

        $result = $renewService->processPaidRenewOrder($legacyOrder);

        $this->assertNotNull($result);
        $this->assertSame((int) $invoice->id, (int) ($result->invoice_id ?? 0));
        $this->assertSame(0, (int) ($result->order_id ?? 0));
        $this->assertSame((int) $invoice->id, (int) (($result->provision_data ?? [])['last_renew_invoice_id'] ?? 0));
        $this->assertSame('local_invoice', (string) (($result->provision_data ?? [])['last_renew_source'] ?? ''));

        $storedOrder = $legacyOrder->fresh();
        $this->assertSame(OrderStatus::PENDING, (int) ($storedOrder?->status ?? -1));
        $this->assertSame('0.00', number_format((float) ($storedOrder?->paid_amount ?? 0), 2, '.', ''));
    }

    #[Test]
    public function process_paid_renew_order_without_bound_invoice_returns_null_instead_of_falling_back_to_legacy_order_flow(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'renew-order-no-invoice-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Renew Order No Invoice',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
        $this->mirrorUserToIdc($user, 'renew-order-no-invoice-'.$suffix);

        $product = Product::query()->create([
            'name' => 'Renew Order No Invoice Product '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '61.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
        ]);
        $this->mirrorProductToIdc($product, $suffix);

        $service = ServiceInstance::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'source_invoice_id' => null,
            'name' => 'Renew Order No Invoice Service '.$suffix,
            'service_no' => 'SVCRENEWNOINV'.$suffix,
            'instance_identifier' => 'renew-no-invoice-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'renewal_price' => '61.00',
            'status' => ServiceStatus::ACTIVE,
            'pricing_snapshot_json' => ['monthly' => '61.00'],
            'provision_snapshot_json' => [],
            'expires_at' => Carbon::parse('2026-06-25 00:00:00'),
            'auto_renew' => 0,
            'invoice_id' => null,
            'order_id' => null,
        ]);

        $legacyOrder = Order::query()->create([
            'order_no' => 'ORDRENEWNOINV'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_id' => (int) $service->id,
            'type' => 'renew',
            'amount' => '61.00',
            'discount' => '0.00',
            'paid_amount' => '61.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);

        $renewService = new ServiceRenewService(
            new InvoiceService,
            new ProviderResolver(
                new ProviderRegistry([
                    new HostingPanelApiDriver($this->createMock(HostingPanelApiTransport::class)),
                ])
            ),
            $this->createMock(CouponService::class),
            $this->createMock(OperationLogService::class),
            new class extends SettingService
            {
                public function getAutomationConfig(): array
                {
                    return array_merge(parent::defaultAutomationConfig(), [
                        'expire_unsuspend_notify_enabled' => false,
                    ]);
                }
            },
            $this->createMock(NotificationService::class),
        );

        $result = $renewService->processPaidRenewOrder($legacyOrder);

        $this->assertNull($result);
        $storedOrder = $legacyOrder->fresh();
        $this->assertSame(OrderStatus::PAID, (int) ($storedOrder?->status ?? -1));
        $this->assertNull($service->fresh()?->invoice_id);
        $this->assertNull(($service->fresh()?->provision_data ?? [])['last_renew_invoice_id'] ?? null);
    }
}
