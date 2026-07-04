<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\AccountTransaction;
use App\Models\IntegrationPlugin;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Integrations\Payments\Data\PaymentRefundRequest;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginScanner;
use Caiwu\Plugins\Gateways\YiPay\Lib\YiPayClient;
use Caiwu\Plugins\Gateways\YiPay\YiPayPlugin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class YiPayGatewayPluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanPluginTables();
        $this->forgetPaymentRuntime();
        config(['app.frontend_url' => 'https://pay.example.test']);
    }

    protected function tearDown(): void
    {
        $this->cleanPluginTables();
        $this->forgetPaymentRuntime();

        parent::tearDown();
    }

    public function test_yipay_manifest_exposes_payment_gateway_config(): void
    {
        $manifest = app(PluginScanner::class)->requireManifest('payment', 'yi_pay');

        $this->assertSame(PaymentGatewayCode::YIPAY, $manifest->key);
        $this->assertSame(YiPayPlugin::class, $manifest->entryClass);
        $this->assertContains('precreate', $manifest->capabilities);
        $this->assertContains('notify_verify', $manifest->capabilities);

        $configKeys = collect($manifest->configSchema)->pluck('key')->all();
        $this->assertContains('merchant_id', $configKeys);
        $this->assertContains('merchant_key', $configKeys);
        $this->assertContains('payment_type', $configKeys);
        $this->assertNotContains('api_base_url', $configKeys);
        $this->assertNotContains('notify_url', $configKeys);
        $this->assertNotContains('ssl_verify', $configKeys);
        $this->assertNotContains('ca_bundle', $configKeys);
    }

    public function test_yipay_precreate_signs_form_payload_and_normalizes_qrcode(): void
    {
        $this->loadYiPayPlugin();
        $sentPayload = [];

        Http::fake(function ($request) use (&$sentPayload) {
            parse_str($request->body(), $sentPayload);

            return Http::response([
                'code' => 1,
                'msg' => 'ok',
                'O_id' => 'ZPAY123',
                'trade_no' => 'PAY202607020001',
                'qrcode' => 'https://zpayz.cn/pay/alipay/ZPAY123/',
            ]);
        });

        $client = new YiPayClient($this->config());
        $result = $client->precreate('PAY202607020001', 12.3, 'Caiwu 测试订单');

        $this->assertSame('https://zpayz.cn/pay/alipay/ZPAY123/', $result['qr_code']);
        $this->assertSame('PAY202607020001', $result['out_trade_no']);
        $this->assertSame('merchant-10001', $sentPayload['pid'] ?? null);
        $this->assertSame('alipay', $sentPayload['type'] ?? null);
        $this->assertSame('PAY202607020001', $sentPayload['out_trade_no'] ?? null);
        $this->assertSame('12.30', $sentPayload['money'] ?? null);
        $this->assertSame('https://pay.example.test/api/client/payment/notify/yipay', $sentPayload['notify_url'] ?? null);
        $this->assertSame('MD5', $sentPayload['sign_type'] ?? null);
        $this->assertSame($this->sign($sentPayload), $sentPayload['sign'] ?? null);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://zpayz.cn/mapi.php');
    }

    public function test_yipay_query_and_refund_map_provider_responses(): void
    {
        $this->loadYiPayPlugin();
        $requests = [];

        Http::fake(function ($request) use (&$requests) {
            $queryPayload = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $queryPayload);
            $bodyPayload = [];
            parse_str($request->body(), $bodyPayload);
            $payload = array_merge($queryPayload, $bodyPayload);
            $requests[] = $payload;

            if (($payload['act'] ?? '') === 'order') {
                return Http::response([
                    'code' => 1,
                    'msg' => '查询订单号成功！',
                    'trade_no' => 'YIPAY202607020001',
                    'out_trade_no' => 'PAY202607020001',
                    'type' => 'alipay',
                    'pid' => 'merchant-10001',
                    'money' => '12.30',
                    'status' => 1,
                ]);
            }

            return Http::response([
                'code' => 1,
                'msg' => '退款成功',
            ]);
        });

        $client = new YiPayClient($this->config());
        $query = $client->query('PAY202607020001');
        $refund = $client->refund(new PaymentRefundRequest(
            outTradeNo: 'PAY202607020001',
            refundAmount: 12.3,
            refundReason: '测试退款',
            tradeNo: 'YIPAY202607020001',
            outRequestNo: 'RF202607020001',
        ));

        $this->assertSame('TRADE_SUCCESS', $query['trade_status']);
        $this->assertSame('YIPAY202607020001', $query['trade_no']);
        $this->assertSame('12.30', $query['total_amount']);
        $this->assertSame('12.30', $refund['refund_fee']);
        $this->assertSame('PAY202607020001', $refund['out_trade_no']);
        $this->assertSame('merchant-10001', $requests[0]['pid'] ?? null);
        $this->assertSame('secret-key', $requests[0]['key'] ?? null);
        $this->assertSame('refund', $requests[1]['act'] ?? null);
        $this->assertSame('YIPAY202607020001', $requests[1]['trade_no'] ?? null);
    }

    public function test_yipay_notify_verification_uses_md5_canonical_payload(): void
    {
        $this->loadYiPayPlugin();
        $client = new YiPayClient($this->config());
        $payload = [
            'pid' => 'merchant-10001',
            'name' => 'Caiwu 测试订单',
            'money' => '12.30',
            'out_trade_no' => 'PAY202607020001',
            'trade_no' => 'YIPAY202607020001',
            'param' => '',
            'trade_status' => 'TRADE_SUCCESS',
            'type' => 'alipay',
            'sign_type' => 'MD5',
        ];
        $payload['sign'] = $this->sign($payload);

        $this->assertTrue($client->verifyNotify($payload));

        $payload['money'] = '13.30';
        $this->assertFalse($client->verifyNotify($payload));
    }

    public function test_yipay_get_notify_callback_completes_recharge_idempotently(): void
    {
        $this->activateYiPayPlugin();

        $suffix = bin2hex(random_bytes(4));
        $tradeNo = 'YIPAY'.now()->format('YmdHis').strtoupper($suffix);
        $user = User::query()->create([
            'email' => 'yipay-callback-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '15'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'YiPay Callback',
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
        $user->forceFill(['balance' => '0.00'])->save();

        $payment = Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'invoice_id' => null,
            'gateway' => PaymentGatewayCode::YIPAY,
            'amount' => '12.30',
            'status' => PaymentStatus::PENDING,
            'trace_id' => 'test-yipay-callback',
        ]);

        $payload = [
            'pid' => 'merchant-10001',
            'name' => 'Caiwu 测试充值',
            'money' => '12.30',
            'out_trade_no' => (string) $payment->payment_no,
            'trade_no' => $tradeNo,
            'param' => '',
            'trade_status' => 'TRADE_SUCCESS',
            'type' => 'alipay',
            'sign_type' => 'MD5',
        ];
        $payload['sign'] = $this->sign($payload);
        $url = '/api/client/payment/notify/yipay?'.http_build_query($payload);

        $this->get($url)
            ->assertOk()
            ->assertSeeText('success');

        $this->get($url)
            ->assertOk()
            ->assertSeeText('success');

        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $this->assertSame($tradeNo, (string) $payment->trade_no);
        $this->assertNotNull($payment->invoice_id);

        $this->assertSame(1, AccountTransaction::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', 'recharge')
            ->where('source_id', (int) $payment->id)
            ->count());
        $this->assertSame('12.30', User::query()->findOrFail($user->id)->balance);
        $this->assertInstanceOf(Invoice::class, Invoice::query()->find((int) $payment->invoice_id));
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        return [
            'enabled' => true,
            'merchant_id' => 'merchant-10001',
            'merchant_key' => 'secret-key',
            'payment_type' => 'alipay',
            'device' => 'pc',
        ];
    }

    private function loadYiPayPlugin(): void
    {
        $manifest = app(PluginScanner::class)->requireManifest('payment', 'yi_pay');
        app(PluginFileLoader::class)->ensureLoaded($manifest);
    }

    private function activateYiPayPlugin(): IntegrationPlugin
    {
        $scanner = app(PluginScanner::class);
        $installer = app(PluginInstaller::class);
        $configRepository = app(PluginConfigRepository::class);

        $manifest = $scanner->requireManifest('payment', 'yi_pay');
        $plugin = $installer->install('payment', 'yi_pay');
        $configRepository->save($plugin, $manifest, $this->config());

        $enabled = $installer->enable($plugin);
        $this->forgetPaymentRuntime();

        return $enabled;
    }

    private function cleanPluginTables(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        if (Schema::hasTable('integration_plugin_configs')) {
            DB::table('integration_plugin_configs')->truncate();
        }
        DB::table('integration_plugins')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function forgetPaymentRuntime(): void
    {
        $this->app->forgetInstance(PaymentGatewayRegistry::class);
        $this->app->forgetInstance(PaymentGatewayManager::class);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sign(array $payload): string
    {
        unset($payload['sign'], $payload['sign_type']);
        ksort($payload, SORT_STRING);

        $pairs = [];
        foreach ($payload as $key => $value) {
            if ($value === null || $value === '' || is_array($value) || is_object($value)) {
                continue;
            }

            $pairs[] = $key.'='.(string) $value;
        }

        return md5(implode('&', $pairs).'secret-key');
    }
}
