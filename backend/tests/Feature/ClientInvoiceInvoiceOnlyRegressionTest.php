<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientInvoiceInvoiceOnlyRegressionTest extends TestCase
{
    private function makeLegacyUserId(string $suffix): int
    {
        return 900000 + (hexdec(substr(md5('client-invoice-'.$suffix), 0, 5)) % 90000);
    }

    private function mirrorUserToIdc(User $user, string $suffix): void
    {
        DB::connection()->table('users')->updateOrInsert(
            ['id' => (int) $user->id],
            [
                'email' => $user->email,
                'phone' => $user->phone,
                'password' => Hash::make('Temp@123456'),
                'status' => 1,
                'referral_code' => 'C'.strtoupper(substr(md5($suffix.'-'.$user->id), 0, 8)),
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
            ]
        );
    }

    private function mirrorProductToIdc(Product $product, string $suffix): void
    {
        DB::connection()->table('products')->updateOrInsert(
            ['id' => (int) $product->id],
            [
                'product_group_id' => (int) ($product->product_group_id ?: 0) ?: null,
                'product_type' => (string) ($product->product_type ?: 'other'),
                'remark' => null,
                'meta_title' => null,
                'meta_description' => null,
                'meta_keywords' => null,
                'pricing' => json_encode($product->pricing ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'setup_fee' => number_format((float) ($product->setup_fee ?? 0), 2, '.', ''),
                'config_options' => json_encode($product->config_options ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'purchase_requires' => json_encode($product->purchase_requires ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'stock' => (int) ($product->stock ?? 0),
                'status' => 1,
                'sort_order' => 0,
                'provision_module' => null,
                'auto_setup' => 0,
                'supplier_id' => null,
                'supplier_product_id' => null,
                'deleted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function mirrorServiceInstanceToIdc(Service $service, string $suffix): void
    {
        $this->mirrorServiceCompatToIdc([
            'id' => (int) $service->id,
            'user_id' => (int) $service->user_id,
            'product_id' => (int) $service->product_id,
            'source_invoice_id' => (int) (($service->provision_snapshot_json['source_invoice_id'] ?? 0) ?: 0) ?: null,
            'name' => (string) $service->name,
            'domain' => 'client-invoice-'.$suffix.'.example.com',
            'billing_cycle' => (string) $service->billing_cycle,
            'amount' => number_format((float) $service->amount, 2, '.', ''),
            'pricing_snapshot_json' => [],
            'status' => (int) $service->status,
            'provision_snapshot_json' => $service->provision_snapshot_json ?? [],
            'expires_at' => $service->expires_at,
            'auto_renew' => (int) $service->auto_renew,
            'remark' => null,
            'trace_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_client_invoice_summary_uses_total_amount_for_invoice_only_records(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->forceCreate([
            'id' => $this->makeLegacyUserId($suffix.'-summary'),
            'email' => 'client-invoice-summary-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Client Invoice Summary',
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
        $this->mirrorUserToIdc($user, $suffix);

        Invoice::query()->create([
            'invoice_no' => 'CLISUMA'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'type' => 'normal',
            'amount' => '18.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'billing_cycle' => 'monthly',
            'product_spec_snapshot' => '未支付账单 A',
            'due_date' => now()->addDay(),
        ]);

        Invoice::query()->create([
            'invoice_no' => 'CLISUMB'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'type' => 'normal',
            'amount' => '22.50',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::OVERDUE,
            'billing_cycle' => 'monthly',
            'product_spec_snapshot' => '逾期账单 B',
            'due_date' => now()->subDay(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/client/invoices/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.unpaid', 1)
            ->assertJsonPath('data.overdue', 1)
            ->assertJsonPath('data.unpaid_amount', '40.50');
    }

    public function test_client_invoice_show_returns_product_config_without_order_binding(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->forceCreate([
            'id' => $this->makeLegacyUserId($suffix.'-show'),
            'email' => 'client-invoice-show-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Client Invoice Show',
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
        $this->mirrorUserToIdc($user, $suffix);

        $group = ProductCategory::query()->create([
            'parent_id' => null,
            'product_type' => 'server',
            'name' => 'Client Invoice Group '.$suffix,
            'slug' => 'client-invoice-group-'.$suffix,
            'slogan' => '',
            'is_visible' => 1,
            'sort_order' => 0,
        ]);

        $product = Product::query()->create([
            'product_group_id' => (int) $group->id,
            'name' => 'Client Invoice Product '.$suffix,
            'product_type' => 'server',
            'description' => '',
            'pricing' => ['monthly' => '66.00'],
            'setup_fee' => '0.00',
            'config_options' => [
                ['field' => 'cpu', 'name' => 'CPU', 'option_type' => 6],
            ],
            'purchase_requires' => [],
            'stock' => 8,
            'status' => 1,
            'sort_order' => 0,
            'provision_module' => null,
            'auto_setup' => 0,
        ]);
        $this->mirrorProductToIdc($product, $suffix);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'CLISHOW'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'type' => 'normal',
            'amount' => '66.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'billing_cycle' => 'monthly',
            'product_spec_snapshot' => '客户端云主机 2核4G',
            'config_snapshot' => ['cpu' => '2'],
            'due_date' => now()->addDay(),
        ]);

        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => 'Client Invoice Service '.$suffix,
            'domain' => 'client-invoice-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'amount' => '66.00',
            'status' => 0,
            'locked_pricing' => [],
            'provision_data' => [],
            'expires_at' => now()->addMonth(),
            'auto_renew' => 0,
        ]);

        // 更新 Invoice 的 service_id
        $invoice->service_id = (int) $service->id;
        $invoice->save();

        $this->mirrorServiceInstanceToIdc($service, $suffix);

        Sanctum::actingAs($user);

        $this->getJson('/api/client/invoices/'.$invoice->id)
            ->assertOk()
            ->assertJsonPath('data.product.id', (int) $product->id)
            ->assertJsonPath('data.product.config_options.0.field', 'cpu')
            ->assertJsonPath('data.service.id', (int) $service->id)
            ->assertJsonPath('data.product_display_name', '客户端云主机 2核4G');
    }

    public function test_client_invoice_list_filters_refunded_invoice_without_order_binding(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->forceCreate([
            'id' => $this->makeLegacyUserId($suffix.'-refund'),
            'email' => 'client-invoice-refund-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Client Invoice Refund',
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
        $this->mirrorUserToIdc($user, $suffix);

        $refundedInvoice = Invoice::query()->create([
            'invoice_no' => 'CLIREFUND'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'type' => 'normal',
            'amount' => '15.00',
            'paid_amount' => '15.00',
            'status' => InvoiceStatus::REFUNDED,
            'billing_cycle' => 'monthly',
            'product_spec_snapshot' => '已退款账单',
            'due_date' => now()->addDay(),
            'paid_at' => now()->subMinute(),
        ]);

        Payment::query()->create([
            'payment_no' => 'CLIREFPAY'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $refundedInvoice->id,
            'gateway' => 'balance',
            'amount' => '15.00',
            'status' => PaymentStatus::REFUNDED,
            'paid_at' => now()->subMinute(),
            'callback_raw' => [
                'refund' => [
                    'refund_method' => 'balance',
                    'refund_reason' => 'invoice only list filter',
                    'refunded_at' => now()->format('Y-m-d H:i:s'),
                ],
            ],
        ]);

        Invoice::query()->create([
            'invoice_no' => 'CLINORMAL'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'type' => 'normal',
            'amount' => '18.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'billing_cycle' => 'monthly',
            'product_spec_snapshot' => '普通账单',
            'due_date' => now()->addDay(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/client/invoices?status=5&page_size=20')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.invoice_no', (string) $refundedInvoice->invoice_no)
            ->assertJsonPath('data.list.0.status', InvoiceStatus::REFUNDED);
    }
}
