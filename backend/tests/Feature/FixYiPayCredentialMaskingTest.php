<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\GatewayLog;
use Caiwu\Plugins\Gateways\YiPay\Lib\YiPayClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../../plugins/gateways/yi_pay/lib/YiPayClient.php';

/**
 * M-34 回归（易支付侧）：查询与预下单会把商户密钥放进 payload["key"]，
 * YiPayClient 必须在 Log::info / recordFailure 之前本地脱敏，
 * 不把商户密钥交给日志层（GatewayLogService 的兜底脱敏见 FixGatewayLogRedactionTest）。
 */
class FixYiPayCredentialMaskingTest extends TestCase
{
    use DatabaseTransactions;

    private const MERCHANT_KEY = 'test-merchant-key';

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function client(array $overrides = []): YiPayClient
    {
        return new YiPayClient(array_merge([
            'merchant_id' => '10001',
            'merchant_key' => self::MERCHANT_KEY,
            'sign_type' => 'MD5',
            'api_endpoint' => 'https://pay.example.com/',
            'payment_types' => ['alipay'],
        ], $overrides));
    }

    public function test_masked_for_log_masks_merchant_key_and_signature(): void
    {
        $client = $this->client();
        $method = new ReflectionMethod($client, 'maskedForLog');
        $method->setAccessible(true);

        $masked = $method->invoke($client, [
            'act' => 'order',
            'pid' => '10001',
            'out_trade_no' => 'PAY-MASK-1',
            'key' => self::MERCHANT_KEY,
            'sign' => str_repeat('a', 32),
            'sign_type' => 'MD5',
            'nested' => [
                'merchant_key' => self::MERCHANT_KEY,
                'payload' => self::MERCHANT_KEY,
            ],
        ]);

        $encoded = (string) json_encode($masked);
        $this->assertStringNotContainsString(self::MERCHANT_KEY, $encoded);
        $this->assertStringNotContainsString(str_repeat('a', 32), $encoded);
        // sign_type 是签名口径（MD5/RSA）而非密文，保留原值。
        $this->assertSame('MD5', $masked['sign_type']);
        // 业务字段不受影响。
        $this->assertSame('order', $masked['act']);
        $this->assertSame('PAY-MASK-1', $masked['out_trade_no']);
    }

    public function test_failed_precreate_never_persists_plaintext_merchant_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'code' => 0,
                'msg' => 'fail',
                'key' => self::MERCHANT_KEY,
            ]),
        ]);

        try {
            $this->client()->precreate('PAY-MASK-2', 10.0, '账户充值');
            $this->fail('预下单失败未被抛出');
        } catch (BusinessException) {
            // 预期：网关返回 code=0
        }

        $log = GatewayLog::query()->where('out_trade_no', 'PAY-MASK-2')->latest('id')->first();
        $this->assertNotNull($log, '预下单失败未落 gateway_logs');

        $requestData = (string) json_encode($log->request_data);
        $responseData = (string) json_encode($log->response_data);

        $this->assertStringNotContainsString(self::MERCHANT_KEY, $requestData);
        $this->assertStringNotContainsString(self::MERCHANT_KEY, $responseData);
    }

    public function test_failed_query_never_persists_plaintext_merchant_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'code' => 0,
                'msg' => 'fail',
                'key' => self::MERCHANT_KEY,
            ]),
        ]);

        try {
            $this->client()->query('PAY-MASK-3');
            $this->fail('查询失败未被抛出');
        } catch (BusinessException) {
            // 预期：网关返回 code=0
        }

        $log = GatewayLog::query()->where('out_trade_no', 'PAY-MASK-3')->latest('id')->first();
        $this->assertNotNull($log, '查询失败未落 gateway_logs');

        $this->assertStringNotContainsString(self::MERCHANT_KEY, (string) json_encode($log->request_data));
        $this->assertStringNotContainsString(self::MERCHANT_KEY, (string) json_encode($log->response_data));
    }
}
