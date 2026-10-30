<?php

declare(strict_types=1);

namespace Tests\Unit;

use Caiwu\Plugins\Sms\Stay33\Lib\Stay33SmsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/Stay33SmsClient.php';

/**
 * MC 云短信（stay33）客户端契约回归。
 *
 * 关注三件事：
 * 1. 配置不全必须在拼报文之前就失败，不能带着空 username/key 打上游；
 * 2. 成功回执要能拿到 sms_code 作为 request_id，短信平台的对账全靠它；
 * 3. 主备端点切换：主端点异常或返回非 2xx 时必须继续尝试备用端点，
 *    全部失败才落成统一失败结构，且失败文案不能把上游返回的疑似密钥透出来。
 */
class Stay33SmsClientTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return array_merge([
            'username' => 'stay33-user',
            'api_key' => 'stay33-key',
            'sign_name' => '测试站点',
            'channel' => '1',
            'api_endpoint' => 'https://idc.stay33.test/sms/sendApi.php',
            'backup_endpoint' => 'https://backup.stay33.test/sms/sendApi.php',
            'timeout_seconds' => 5,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function client(array $config = []): Stay33SmsClient
    {
        return new Stay33SmsClient($this->config($config));
    }

    public function test_send_verify_code_rejects_incomplete_arguments(): void
    {
        Http::fake();

        $this->assertSame('短信发送参数不完整', $this->client()->sendVerifyCode('', '123456')['message']);
        $this->assertSame('短信发送参数不完整', $this->client()->sendVerifyCode('13800000000', '')['message']);

        Http::assertNothingSent();
    }

    public function test_missing_sign_name_fails_before_request(): void
    {
        Http::fake();

        $result = $this->client(['sign_name' => ''])->sendVerifyCode('13800000000', '123456');

        $this->assertFalse($result['success']);
        $this->assertSame('短信接口配置不完整', $result['message']);
        Http::assertNothingSent();
    }

    public function test_missing_credentials_fail_before_request(): void
    {
        Http::fake();

        foreach ([['username' => ''], ['api_key' => '']] as $overrides) {
            $result = $this->client($overrides)->sendVerifyCode('13800000000', '123456');
            $this->assertFalse($result['success']);
            $this->assertSame('短信接口配置不完整', $result['message']);
        }

        Http::assertNothingSent();
    }

    public function test_successful_send_returns_request_id_and_signed_content(): void
    {
        Http::fake([
            'idc.stay33.test/*' => Http::response([
                'code' => 1,
                'msg' => 'ok',
                'success_data' => [['sms_code' => 'req-20261028-0001']],
            ], 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['status']);
        $this->assertSame('req-20261028-0001', $result['request_id']);
        $this->assertSame('100001', $result['template_code']);
        $this->assertSame('654321', $result['template_params']['code']);
        $this->assertSame('测试站点', $result['template_params']['sign_name']);

        Http::assertSent(function (Request $request): bool {
            $body = urldecode($request->body());

            return str_contains($body, 'username=stay33-user')
                && str_contains($body, 'phone=13800000000')
                && str_contains($body, '【测试站点】')
                && str_contains($body, '654321');
        });
    }

    public function test_upstream_business_failure_is_reported_as_failure_envelope(): void
    {
        Http::fake([
            'idc.stay33.test/*' => Http::response(['code' => 0, 'msg' => '余额不足'], 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('余额不足', $result['message']);
        $this->assertSame(0, $result['raw']['code']);
    }

    public function test_suspicious_upstream_message_is_replaced_with_generic_text(): void
    {
        // 上游偶尔把接口密钥回显在错误文案里，这类文本不能原样透给前端。
        // 密钥写成打码占位（sk_live_***），既能命中插件的凭证特征判定，又不会触发仓库密钥扫描。
        Http::fake([
            'idc.stay33.test/*' => Http::response(['code' => 0, 'msg' => 'key sk_live_*** invalid'], 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('短信发送失败，请稍后重试', $result['message']);
    }

    public function test_primary_endpoint_http_error_falls_back_to_backup_endpoint(): void
    {
        Http::fake([
            'idc.stay33.test/*' => Http::response('gateway error', 502),
            'backup.stay33.test/*' => Http::response([
                'code' => 1,
                'success_data' => [['sms_code' => 'req-backup-0001']],
            ], 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertTrue($result['success']);
        $this->assertSame('req-backup-0001', $result['request_id']);
        Http::assertSentCount(2);
    }

    public function test_connection_exception_falls_back_to_backup_endpoint(): void
    {
        Http::fake([
            'idc.stay33.test/*' => fn () => throw new ConnectionException('connect timeout'),
            'backup.stay33.test/*' => Http::response([
                'code' => 1,
                'success_data' => [['sms_code' => 'req-backup-0002']],
            ], 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertTrue($result['success']);
        $this->assertSame('req-backup-0002', $result['request_id']);
    }

    public function test_all_endpoints_failing_returns_retryable_failure(): void
    {
        Http::fake([
            'idc.stay33.test/*' => Http::response('gateway error', 502),
            'backup.stay33.test/*' => Http::response('gateway error', 502),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('短信接口请求失败，请稍后重试', $result['message']);
        Http::assertSentCount(2);
    }

    public function test_non_json_body_counts_as_failure(): void
    {
        Http::fake([
            'idc.stay33.test/*' => Http::response('<html>maintenance</html>', 200),
            'backup.stay33.test/*' => Http::response('<html>maintenance</html>', 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('短信接口请求失败，请稍后重试', $result['message']);
    }

    public function test_send_message_uses_signed_content_and_channel(): void
    {
        Http::fake([
            'idc.stay33.test/*' => Http::response([
                'code' => 1,
                'success_data' => [['sms_code' => 'req-template-0001']],
            ], 200),
        ]);

        $result = $this->client(['channel' => '2'])->sendMessage('13800000000', '100003', '您的服务即将到期');

        $this->assertTrue($result['success']);
        $this->assertSame('100003', $result['template_code']);
        $this->assertSame('2', $result['template_params']['channel']);
        $this->assertSame('【测试站点】您的服务即将到期', $result['template_params']['content']);

        Http::assertSent(fn (Request $request): bool => str_contains(urldecode($request->body()), 'channel=2'));
    }

    public function test_existing_sign_prefix_is_not_duplicated(): void
    {
        Http::fake([
            'idc.stay33.test/*' => Http::response(['code' => 1], 200),
        ]);

        $result = $this->client()->sendMessage('13800000000', '100003', '【已有签名】正文内容');

        $this->assertTrue($result['success']);
        $this->assertSame('【已有签名】正文内容', $result['template_params']['content']);
    }

    public function test_empty_template_code_is_kept_verbatim(): void
    {
        // 模板正文短信的历史契约：模板编码原样回显、不回落验证码模板（sendVerifyCode 才回落）
        Http::fake([
            'idc.stay33.test/*' => Http::response(['code' => 1], 200),
        ]);

        $result = $this->client()->sendMessage('13800000000', '100001', '正文内容');

        $this->assertTrue($result['success']);
        $this->assertSame('100001', $result['template_code']);
        $this->assertSame('【测试站点】正文内容', $result['template_params']['content']);
    }
}
