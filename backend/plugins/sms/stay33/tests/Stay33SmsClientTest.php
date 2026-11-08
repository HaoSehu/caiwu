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
 * MC 云短信（stay33）客户端契约回归 —— MCloud v10 开放 API。
 *
 * 契约依据：官方开发文档 freescdn.com/api-docs?id=99（发送短信 /sms/send）。
 *
 * 关注六件事：
 * 1. 配置不全必须在拼报文之前就失败，不能带着空凭据打上游；
 * 2. 鉴权走官方 header（X-Api-Key + X-Uid 或 X-Access-Key + X-Access-Secret），
 *    凭据不得出现在 URL query 里；
 * 3. 成功判定分两层：code=0 只是「接口调用成功」，data.success/failed/blocked
 *    才是个体投递结果 —— 只看 code 会把「全部被拦截」记成发送成功；
 * 4. signature 走官方参数，正文不再由本地拼【签名】；
 * 5. channel_id 官方声明为 int，留空时不得发送该参数；
 * 6. 号码归一只剥大陆 +86 前缀，国际号码原样保留。
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
            'uid' => '123456',
            'api_key' => 'mcloud-key',
            'sign_name' => '测试站点',
            'channel' => '10',
            'api_endpoint' => 'https://api.mcloud.test/api/v1/sms/send',
            'backup_endpoint' => 'https://backup.mcloud.test/api/v1/sms/send',
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

    /**
     * 官方成功响应样例。
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function successBody(array $data = []): array
    {
        return [
            'code' => 0,
            'message' => '发送完成',
            'data' => array_merge([
                'error' => '',
                'total' => 1,
                'success' => 1,
                'failed' => 0,
                'blocked' => [],
                'consumed' => 1,
                'per_count' => 1,
                'message_id' => '2509150001',
                'blocked_count' => 0,
            ], $data),
        ];
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

        foreach ([['uid' => '', 'api_key' => ''], ['uid' => '123', 'api_key' => '']] as $overrides) {
            $result = $this->client($overrides)->sendVerifyCode('13800000000', '123456');
            $this->assertFalse($result['success']);
            $this->assertSame('短信接口配置不完整', $result['message']);
        }

        Http::assertNothingSent();
    }

    public function test_api_key_auth_is_sent_as_headers_not_query(): void
    {
        Http::fake(['api.mcloud.test/*' => Http::response($this->successBody(), 200)]);

        $this->assertTrue($this->client()->sendVerifyCode('13800000000', '654321')['success']);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            // 凭据必须在 header，且绝不能出现在 URL 里
            return $request->hasHeader('X-Api-Key', 'mcloud-key')
                && $request->hasHeader('X-Uid', '123456')
                && ! array_key_exists('api_key', $query)
                && ! array_key_exists('uid', $query);
        });
    }

    public function test_legacy_config_keys_are_accepted(): void
    {
        Http::fake(['api.mcloud.test/*' => Http::response($this->successBody(), 200)]);

        // 旧配置：username + key，没有 uid / api_key
        $client = new Stay33SmsClient([
            'username' => 'legacy-user',
            'key' => 'legacy-key',
            'sign_name' => '测试站点',
            'api_endpoint' => 'https://api.mcloud.test/api/v1/sms/send',
            'backup_endpoint' => '',
        ]);

        $this->assertTrue($client->sendVerifyCode('13800000000', '123456')['success']);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Uid', 'legacy-user')
            && $request->hasHeader('X-Api-Key', 'legacy-key'));
    }

    public function test_blank_uid_falls_back_to_user_id(): void
    {
        Http::fake(['api.mcloud.test/*' => Http::response($this->successBody(), 200)]);

        $client = new Stay33SmsClient([
            'uid' => '   ',
            'user_id' => 'fallback-user',
            'api_key' => 'k',
            'sign_name' => '测试站点',
            'api_endpoint' => 'https://api.mcloud.test/api/v1/sms/send',
            'backup_endpoint' => '',
        ]);

        $this->assertTrue($client->sendVerifyCode('13800000000', '123456')['success']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('X-Uid', 'fallback-user'));
    }

    public function test_successful_send_uses_official_params(): void
    {
        Http::fake(['api.mcloud.test/*' => Http::response($this->successBody(), 200)]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertTrue($result['success']);
        $this->assertSame('2509150001', $result['request_id']);
        $this->assertSame('100001', $result['template_code']);
        $this->assertSame('654321', $result['template_params']['code']);

        Http::assertSent(function (Request $request): bool {
            $this->assertSame('GET', $request->method());

            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $content = (string) ($query['content'] ?? '');

            return ($query['phones'] ?? '') === '13800000000'
                && ($query['signature'] ?? '') === '测试站点'
                && ($query['channel_id'] ?? '') === '10'
                && str_contains($content, '654321')
                // 签名走参数，正文不得再出现【签名】
                && ! str_contains($content, '【测试站点】');
        });
    }

    public function test_send_message_uses_signature_param(): void
    {
        Http::fake(['api.mcloud.test/*' => Http::response($this->successBody(), 200)]);

        $result = $this->client()->sendMessage('13800000000', '100003', '您的服务即将到期');

        $this->assertTrue($result['success']);
        $this->assertSame('100003', $result['template_code']);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['signature'] ?? '') === '测试站点'
                && ($query['content'] ?? '') === '您的服务即将到期';
        });
    }

    public function test_empty_channel_omits_channel_id(): void
    {
        // 官方 channel_id 为 int，不传走默认通道；空串不能作为参数发出。
        Http::fake(['api.mcloud.test/*' => Http::response($this->successBody(), 200)]);

        $this->assertTrue($this->client(['channel' => ''])->sendMessage('13800000000', '100003', '正文')['success']);

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ! array_key_exists('channel_id', $query);
        });
    }

    public function test_all_numbers_blocked_is_reported_as_failure(): void
    {
        // 官方 code=0 只代表接口调用成功；全部号码被拦截时 success=0，不能记成功。
        Http::fake([
            'api.mcloud.test/*' => Http::response($this->successBody([
                'total' => 1,
                'success' => 0,
                'failed' => 1,
                'blocked' => [['phone' => '138****0000', 'reason' => '黑名单']],
                'blocked_count' => 1,
                'error' => '号码命中黑名单',
            ]), 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('号码命中黑名单', $result['message']);
    }

    public function test_partial_failure_is_reported_as_failure(): void
    {
        Http::fake([
            'api.mcloud.test/*' => Http::response($this->successBody([
                'total' => 2,
                'success' => 1,
                'failed' => 1,
                'blocked_count' => 0,
            ]), 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('部分投递失败', $result['message']);
    }

    public function test_minimal_success_response_without_counts_still_succeeds(): void
    {
        // 精简响应只有 code/message 时不做投递计数判定，保持兼容
        Http::fake(['api.mcloud.test/*' => Http::response(['code' => 0, 'message' => 'ok'], 200)]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertTrue($result['success']);
        $this->assertNull($result['request_id']);
    }

    public function test_string_zero_code_is_treated_as_success(): void
    {
        // 上游 JSON 里 code 可能是字符串 "0"
        Http::fake(['api.mcloud.test/*' => Http::response(['code' => '0', 'message' => 'ok'], 200)]);

        $this->assertTrue($this->client()->sendVerifyCode('13800000000', '654321')['success']);
    }

    public function test_legacy_success_code_one_is_now_a_failure(): void
    {
        Http::fake(['api.mcloud.test/*' => Http::response(['code' => 1, 'message' => '鉴权失败'], 200)]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('鉴权失败', $result['message']);
    }

    public function test_missing_code_field_counts_as_failure(): void
    {
        Http::fake(['api.mcloud.test/*' => Http::response(['message' => '参数缺失'], 200)]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('参数缺失', $result['message']);
    }

    public function test_upstream_failure_prefers_message_over_msg(): void
    {
        Http::fake([
            'api.mcloud.test/*' => Http::response(['code' => 4001, 'message' => '余额不足', 'msg' => '旧字段文案'], 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('余额不足', $result['message']);
    }

    public function test_suspicious_upstream_message_is_replaced_with_generic_text(): void
    {
        // 上游偶尔把接口密钥回显在错误文案里，这类文本不能原样透给前端。
        Http::fake([
            'api.mcloud.test/*' => Http::response(['code' => 1002, 'message' => 'api_key sk_live_*** invalid'], 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('短信发送失败，请稍后重试', $result['message']);
    }

    public function test_mainland_phone_with_country_code_is_normalized(): void
    {
        Http::fake(['api.mcloud.test/*' => Http::response($this->successBody(), 200)]);

        foreach (['+8613800000000', '8613800000000', '008613800000000', '138 0000 0000'] as $phone) {
            $this->assertTrue($this->client()->sendVerifyCode($phone, '654321')['success'], $phone);
        }

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['phones'] ?? '') === '13800000000';
        });
    }

    public function test_international_phone_is_left_untouched(): void
    {
        // 官方只要求号码分隔符格式，未声明可去掉国际前缀；本地不得改写号码语义
        Http::fake(['api.mcloud.test/*' => Http::response($this->successBody(), 200)]);

        $this->client()->sendVerifyCode('+852 9123 4567', '654321');

        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['phones'] ?? '') === '+852 9123 4567';
        });
    }

    public function test_primary_endpoint_http_error_falls_back_to_backup_endpoint(): void
    {
        Http::fake([
            'api.mcloud.test/*' => Http::response('gateway error', 502),
            'backup.mcloud.test/*' => Http::response($this->successBody(['message_id' => 'backup-1']), 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertTrue($result['success']);
        $this->assertSame('backup-1', $result['request_id']);
        Http::assertSentCount(2);
    }

    public function test_connection_exception_falls_back_to_backup_endpoint(): void
    {
        Http::fake([
            'api.mcloud.test/*' => fn () => throw new ConnectionException('connect timeout'),
            'backup.mcloud.test/*' => Http::response($this->successBody(), 200),
        ]);

        $this->assertTrue($this->client()->sendVerifyCode('13800000000', '654321')['success']);
    }

    public function test_all_endpoints_failing_returns_retryable_failure(): void
    {
        Http::fake([
            'api.mcloud.test/*' => Http::response('gateway error', 502),
            'backup.mcloud.test/*' => Http::response('gateway error', 502),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('短信接口请求失败，请稍后重试', $result['message']);
    }

    public function test_non_json_body_counts_as_failure(): void
    {
        Http::fake([
            'api.mcloud.test/*' => Http::response('<html>maintenance</html>', 200),
            'backup.mcloud.test/*' => Http::response('<html>maintenance</html>', 200),
        ]);

        $result = $this->client()->sendVerifyCode('13800000000', '654321');

        $this->assertFalse($result['success']);
        $this->assertSame('短信接口请求失败，请稍后重试', $result['message']);
    }

    public function test_code_markers_are_stripped_before_sending(): void
    {
        Http::fake(['api.mcloud.test/*' => Http::response($this->successBody(), 200)]);

        $result = $this->client()->sendMessage('13800000000', '100003', '您的验证码为{codes}123456{/codes}，请勿泄漏');

        $this->assertTrue($result['success']);
        $this->assertSame('您的验证码为123456，请勿泄漏', $result['template_params']['content']);
    }

    public function test_query_templates_maps_official_response(): void
    {
        Http::fake([
            'api.mcloud.test/api/v1/sms/templates' => Http::response([
                'code' => 0,
                'data' => [
                    'list' => [
                        ['id' => 1, 'name' => '登录验证码', 'content' => '您的验证码是 ${code}', 'status' => 1],
                        ['id' => 2, 'name' => '通知', 'content' => '您的服务已到期', 'status' => 0],
                        ['id' => 3, 'name' => '被拒', 'content' => '内容 ${a}', 'status' => 2, 'reason' => '含敏感词'],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->client()->queryTemplates();

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['templates']);
        $this->assertSame('1', $result['templates'][0]['provider_template_id']);
        $this->assertSame('approved', $result['templates'][0]['status']);
        $this->assertSame(['code'], $result['templates'][0]['variables']);
        $this->assertSame('pending', $result['templates'][1]['status']);
        $this->assertSame('rejected', $result['templates'][2]['status']);
        $this->assertSame('含敏感词', $result['templates'][2]['reject_reason']);
    }

    public function test_submit_template_posts_official_payload(): void
    {
        Http::fake([
            'api.mcloud.test/api/v1/sms/templates' => Http::response([
                'code' => 0,
                'data' => ['id' => 8, 'status' => 0],
                'message' => '模板已提交，等待审核',
            ], 200),
        ]);

        $result = $this->client()->submitTemplate(null, '登录验证码', '', '您的验证码是 ${code}', '用于登录');

        $this->assertTrue($result['success']);
        $this->assertSame('8', $result['provider_template_id']);
        $this->assertSame('pending', $result['status']);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->method() === 'POST'
                && ($body['name'] ?? '') === '登录验证码'
                // 内容含「验证码」→ 自动归到 verify 场景
                && ($body['scene'] ?? '') === 'verify'
                && ($body['remark'] ?? '') === '用于登录'
                && ! array_key_exists('id', $body);
        });
    }

    public function test_submit_template_passes_scene_and_sample_image(): void
    {
        // 官方 107：scene 为 verify/notify/marketing，sample_image 可选
        Http::fake([
            'api.mcloud.test/api/v1/sms/templates' => Http::response([
                'code' => 0,
                'data' => ['id' => 12, 'status' => 0],
                'message' => '模板已提交，等待审核',
            ], 200),
        ]);

        $result = $this->client()->submitTemplate(
            null,
            '营销活动',
            'marketing',
            '您有一张优惠券 ${amount} 元待领取',
            '活动通知',
            'https://cdn.example.com/a.png',
        );

        $this->assertTrue($result['success']);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return ($body['scene'] ?? '') === 'marketing'
                && ($body['sample_image'] ?? '') === 'https://cdn.example.com/a.png'
                && ($body['remark'] ?? '') === '活动通知';
        });
    }

    public function test_submit_template_rejects_blank_name_or_content(): void
    {
        Http::fake();

        $this->assertFalse($this->client()->submitTemplate(null, '', 'verify', '内容')['success']);
        $this->assertFalse($this->client()->submitTemplate(null, '名称', 'verify', '')['success']);

        Http::assertNothingSent();
    }

    public function test_submit_template_without_credentials_fails_before_request(): void
    {
        Http::fake();

        $client = new Stay33SmsClient([
            'uid' => '',
            'api_key' => '',
            'sign_name' => '测试站点',
            'api_endpoint' => 'https://api.mcloud.test/api/v1/sms/send',
        ]);

        $result = $client->submitTemplate(null, '名称', 'verify', '内容');

        $this->assertFalse($result['success']);
        $this->assertSame('短信接口配置不完整', $result['message']);
        Http::assertNothingSent();
    }

    public function test_update_template_sends_id(): void
    {
        Http::fake([
            'api.mcloud.test/api/v1/sms/templates' => Http::response(['code' => 0, 'data' => ['id' => 9, 'status' => 0]], 200),
        ]);

        $this->client()->submitTemplate('9', '标题', 'notify', '正文内容');

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return ($body['id'] ?? null) === 9 && ($body['scene'] ?? '') === 'notify';
        });
    }

    public function test_query_template_status_finds_by_id_and_detects_missing(): void
    {
        Http::fake([
            'api.mcloud.test/api/v1/sms/templates' => Http::response([
                'code' => 0,
                'data' => ['list' => [['id' => 8, 'content' => 'x', 'status' => 1]]],
            ], 200),
        ]);

        $found = $this->client()->queryTemplateStatus('8');
        $this->assertTrue($found['success']);
        $this->assertSame('approved', $found['status']);

        $missing = $this->client()->queryTemplateStatus('999');
        $this->assertTrue($missing['success']);
        $this->assertSame('deleted', $missing['status']);
    }

    public function test_query_signatures_maps_official_response(): void
    {
        Http::fake([
            'api.mcloud.test/api/v1/sms/signatures' => Http::response([
                'code' => 0,
                'data' => ['list' => [
                    ['id' => 1, 'name' => '某某科技', 'status' => 1, 'status_text' => '已通过', 'carrier_ok' => true],
                    ['id' => 2, 'name' => '待审签名', 'status' => 0, 'status_text' => '审核中', 'carrier_ok' => false],
                ]],
            ], 200),
        ]);

        $result = $this->client()->querySignatures();

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['signs']);
        $this->assertSame('某某科技', $result['signs'][0]['name']);
        $this->assertSame('approved', $result['signs'][0]['status']);
        $this->assertTrue($result['signs'][0]['carrier_ok']);
        $this->assertSame('pending', $result['signs'][1]['status']);
    }
}
