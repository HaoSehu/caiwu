<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Admin\SupplierController;
use App\Http\Resources\Product\SupplierResource;
use App\Jobs\ProcessPaidOrderFulfillmentJob;
use App\Jobs\ProcessPaidOrderReferralRewardJob;
use App\Jobs\SendPaidInvoiceAdminNotificationJob;
use App\Jobs\SyncPaidInvoiceCouponUsageJob;
use App\Models\Supplier;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceTransformService;
use App\Services\System\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use Tests\TestCase;

class BackendHealthFixRegressionTest extends TestCase
{
    public function test_setting_payload_masks_sensitive_value(): void
    {
        $service = new SettingService;
        $method = (new ReflectionClass($service))->getMethod('formatSettingPayload');
        $method->setAccessible(true);

        $payload = $method->invoke($service, 'payment', 'alipay_private_key', 'plain-private-key');

        $this->assertSame('', $payload['value']);
        $this->assertTrue($payload['is_secret']);
        $this->assertTrue($payload['has_value']);
        $this->assertSame('******', $payload['masked_value']);
    }

    public function test_empty_sensitive_setting_is_not_saved_as_replacement(): void
    {
        $service = new SettingService;
        $method = (new ReflectionClass($service))->getMethod('prepareSettingsForSave');
        $method->setAccessible(true);

        $payload = $method->invoke($service, [
            'alipay_private_key' => '   ',
            'alipay_enabled' => '1',
        ]);

        $this->assertArrayNotHasKey('alipay_private_key', $payload);
        $this->assertSame('1', $payload['alipay_enabled']);
    }

    public function test_supplier_resource_does_not_return_api_key(): void
    {
        $supplier = new Supplier([
            'id' => 1,
            'name' => '测试供应商',
            'code' => 'supplier_test',
            'interface_type' => 'mofang_finance_api',
            'api_username' => 'demo',
            'api_key' => 'secret-value',
            'status' => 1,
        ]);

        $payload = (new SupplierResource($supplier))->toArray(Request::create('/'));

        $this->assertArrayNotHasKey('api_key', $payload);
        $this->assertTrue($payload['has_api_key']);
    }

    public function test_supplier_show_does_not_return_api_key(): void
    {
        $supplier = new Supplier([
            'id' => 1,
            'name' => '测试供应商',
            'code' => 'supplier_test',
            'interface_type' => 'mofang_finance_api',
            'api_url' => 'https://supplier.example.com',
            'api_username' => 'demo',
            'api_key' => 'secret-value',
            'status' => 1,
            'sort_order' => 0,
        ]);
        $supplier->exists = true;

        $response = (new SupplierController)->show($supplier);
        $payload = $response->getData(true);

        $this->assertArrayNotHasKey('api_key', $payload['data']);
        $this->assertTrue($payload['data']['has_api_key']);
    }

    public function test_provider_aware_cache_keys_keep_mofang_independent(): void
    {
        $supplier = new Supplier([
            'interface_type' => 'mofang_finance_api',
        ]);
        $supplier->id = 9;
        $supplier->exists = true;

        $this->assertSame(
            'upstream:mofang_finance_api:product_config_options:9:123',
            app(ServiceTransformService::class)->buildProductConfigOptionsCacheKey($supplier, 123)
        );
        $this->assertSame(
            'upstream:mofang_finance_api:host_modules:9:456',
            app(ServiceDetailService::class)->buildMonitorModuleCacheKey($supplier, 456)
        );

        $this->assertStringContainsString(
            'buildLegacyProductConfigOptionsCacheKey',
            file_get_contents(base_path('app/Services/ClientServiceConsole/ServiceTransformService.php')) ?: ''
        );
        $this->assertStringContainsString(
            'buildLegacyMonitorModuleCacheKey',
            file_get_contents(base_path('app/Services/ClientServiceConsole/ServiceDetailService.php')) ?: ''
        );
    }

    public function test_database_engineering_service_never_deletes_payments(): void
    {
        $source = file_get_contents(base_path('app/Services/System/DatabaseEngineeringService.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString("deleteOrphans('payments'", $source);
        $this->assertStringContainsString('payments_orphan_user_or_invoice_reported', $source);
    }

    public function test_get_group_settings_does_not_persist_fallback_values(): void
    {
        try {
            DB::table('settings')
                ->where('group_key', 'payment')
                ->where('item_key', 'alipay_enabled')
                ->delete();
            Cache::flush();

            (new SettingService)->getGroupSettings('payment');

            $this->assertDatabaseMissing('settings', [
                'group_key' => 'payment',
                'item_key' => 'alipay_enabled',
            ]);
        } catch (\Throwable $exception) {
            if (str_contains($exception->getMessage(), 'Access denied')) {
                $this->markTestSkipped('测试库不可写，已跳过 GET 设置只读数据库断言。');
            }

            throw $exception;
        }
    }

    public function test_payment_service_never_deletes_balance_logs(): void
    {
        $source = file_get_contents(base_path('app/Services/Finance/PaymentService.php'));

        $this->assertIsString($source);
        $this->assertStringNotContainsString('BalanceLog::query()->delete', $source);
        $this->assertStringNotContainsString('->delete();', $source);
    }

    public function test_alipay_refund_does_not_use_undefined_locked_invoice_variable(): void
    {
        $source = file_get_contents(base_path('app/Services/Finance/PaymentService.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('$this->resolveBalanceRefundableAmount($invoice, $payment)', $source);
    }

    public function test_paid_order_jobs_define_runtime_policy(): void
    {
        $this->assertSame(1200, (new ProcessPaidOrderFulfillmentJob(1))->timeout);
        $this->assertSame(300, (new ProcessPaidOrderReferralRewardJob(1))->timeout);
        $this->assertSame(300, (new SendPaidInvoiceAdminNotificationJob(1))->timeout);
        $this->assertSame(300, (new SyncPaidInvoiceCouponUsageJob(1))->timeout);
    }

    public function test_schedule_worker_consumes_declared_queues_with_timeout(): void
    {
        $source = file_get_contents(base_path('routes/console.php'));

        $this->assertIsString($source);
        $this->assertSame('provision,referral,notification,coupon,default', config('queue.caiwu_worker_queues'));
        $this->assertStringContainsString('queue.caiwu_worker_queues', $source);
        $this->assertStringContainsString('queue.caiwu_worker_timeout', $source);
    }

    public function test_vnc_relay_log_masks_token(): void
    {
        $source = file_get_contents(base_path('app/Console/Commands/VncRelayCommand.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString("'token' => \$this->maskToken(\$token)", $source);
    }

    public function test_validation_language_uses_simplified_chinese(): void
    {
        $messages = require base_path('lang/zh_CN/validation.php');

        $this->assertSame('请填写:attribute。', $messages['required']);
        $this->assertSame('真实姓名', $messages['attributes']['realname']);
        $this->assertSame('证件号码', $messages['attributes']['idcard']);
    }
}
