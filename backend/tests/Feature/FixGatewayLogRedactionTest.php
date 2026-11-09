<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentGatewayCode;
use App\Models\GatewayLog;
use App\Services\System\GatewayLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * M-34 回归：易支付查询/预下单会把商户密钥放进 payload["key"]，
 * 该 payload 原样进入 gateway_logs 的 request_data/response_data——
 * 商户密钥等于可伪造该商户一切支付/回调的凭据，任何落库/落文件明细都不得含明文。
 *
 * 覆盖两端口径：
 * 1. GatewayLogService 写库前统一走 AuditParamRedactor + 字符串形态 sign/key 掩码；
 * 2. YiPayClient 在交给日志层前本地脱敏（见 YiPayClient 测试）。
 */
class FixGatewayLogRedactionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(Schema::hasTable('gateway_logs'), '测试库缺少 gateway_logs');
    }

    public function test_merchant_key_in_payload_never_lands_in_request_data(): void
    {
        $merchantKey = 'test-merchant-key-abcdef';

        $log = app(GatewayLogService::class)->record(
            gateway: PaymentGatewayCode::YIPAY,
            action: 'query',
            outTradeNo: 'PAY-REDACT-1',
            requestData: [
                'act' => 'order',
                'pid' => '10001',
                'out_trade_no' => 'PAY-REDACT-1',
                'key' => $merchantKey,
                'sign' => str_repeat('a', 32),
                'sign_type' => 'MD5',
            ],
            responseData: [
                'code' => 1,
                'trade_no' => 'YIPAY-TRADE-1',
                'money' => '1.00',
            ],
            resultStatus: 'success',
        );

        $fresh = GatewayLog::query()->findOrFail($log->id);
        $requestData = (array) $fresh->request_data;

        // 命中凭据键时输出保留前后各 4 字符的掩码，不让明文进入 gateway_logs
        $this->assertSame('test****cdef', $requestData['key']);
        $this->assertStringNotContainsString($merchantKey, (string) json_encode($requestData));
        $this->assertStringNotContainsString($merchantKey, (string) json_encode($fresh->response_data));

        // 非凭据业务字段原样保留（管理端排障需要完整业务报文，见 BACKEND.md §7）。
        $this->assertSame('order', $requestData['act']);
        $this->assertSame('10001', $requestData['pid']);
        $this->assertSame('PAY-REDACT-1', $requestData['out_trade_no']);
    }

    public function test_credential_fields_are_masked_at_any_depth(): void
    {
        $log = app(GatewayLogService::class)->record(
            gateway: PaymentGatewayCode::YIPAY,
            action: 'precreate',
            outTradeNo: 'PAY-REDACT-2',
            requestData: [
                'nested' => [
                    'merchant_key' => 'inner-secret-value',
                    'secret' => 'sk-live-0123456789',
                    'private_key' => 'MIIEvPLAINTEXT',
                    'token' => 'tok_abcdef',
                    'notify_url' => 'https://example.test/notify',
                ],
            ],
            responseData: [],
            resultStatus: 'failed',
            errorMsg: 'x',
        );

        $requestData = (array) GatewayLog::query()->findOrFail($log->id)->request_data;
        $nested = (array) $requestData['nested'];

        foreach (['inner-secret-value', 'sk-live-0123456789', 'MIIEvPLAINTEXT', 'tok_abcdef'] as $secret) {
            $this->assertStringNotContainsString($secret, (string) json_encode($requestData));
        }

        $this->assertSame('https://example.test/notify', $nested['notify_url']);
    }

    public function test_string_payload_sign_and_key_pairs_are_masked(): void
    {
        // 响应体原样塞进数组的形态（query-string / JSON 文本）：按 sign、key 键值对掩码。
        $log = app(GatewayLogService::class)->record(
            gateway: PaymentGatewayCode::YIPAY,
            action: 'query',
            outTradeNo: 'PAY-REDACT-3',
            requestData: [
                'raw_body' => 'act=order&pid=10001&key=abcdef0123456789abcdef&sign=deadbeefdeadbeef',
            ],
            responseData: [
                'raw_json' => '{"code":1,"key":"abcdef0123456789abcdef","sign":"deadbeefdeadbeef"}',
            ],
            resultStatus: 'success',
        );

        $fresh = GatewayLog::query()->findOrFail($log->id);
        $requestData = (array) $fresh->request_data;
        $responseData = (array) $fresh->response_data;

        $this->assertStringNotContainsString('abcdef0123456789abcdef', (string) $requestData['raw_body']);
        $this->assertStringNotContainsString('deadbeefdeadbeef', (string) $requestData['raw_body']);
        $this->assertStringNotContainsString('abcdef0123456789abcdef', (string) $responseData['raw_json']);
        $this->assertStringNotContainsString('deadbeefdeadbeef', (string) $responseData['raw_json']);

        // 掩码保留前后各 4 字符，便于核对是哪一份凭据。
        $this->assertStringContainsString('abcd****cdef', (string) $requestData['raw_body']);
    }

    public function test_business_words_containing_key_are_not_masked(): void
    {
        $log = app(GatewayLogService::class)->record(
            gateway: PaymentGatewayCode::YIPAY,
            action: 'query',
            outTradeNo: 'PAY-REDACT-4',
            requestData: [
                'keyword' => '搜索关键词',
                'monkey' => 'abc',
                'hotkey' => 'F5',
            ],
            responseData: [],
            resultStatus: 'success',
        );

        $requestData = (array) GatewayLog::query()->findOrFail($log->id)->request_data;

        $this->assertSame('搜索关键词', $requestData['keyword']);
        $this->assertSame('abc', $requestData['monkey']);
        $this->assertSame('F5', $requestData['hotkey']);
    }
}
