<?php

declare(strict_types=1);

namespace Tests\Unit;

use Caiwu\Plugins\Sms\Stay33\Lib\Stay33SmsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/Stay33SmsClient.php';
require_once __DIR__.'/../lib/Stay33SmsService.php';

/**
 * MC 云短信（stay33）服务层契约回归 —— 对齐上游 Mcloudsmsv10 v2.0 对接包。
 *
 * 关注三件事：
 * 1. 参考插件的 `config/smsTemplate.php` 20 条默认模板必须完整落地，
 *    `@var()` 语法要翻译成平台既有的 `${var}`，变量清单与文案一致；
 * 2. 参考插件按场景内置的验证码文案要通过 `sms.verify_code_template` 下发，
 *    这是平台「同步验证码模板」的数据来源；
 * 3. 模板动作返回的 content 必须是已翻译的语法，不能把 `@var()` 原样落库。
 */
class Stay33SmsServiceTest extends TestCase
{
    /**
     * 官方成功响应样例（含投递计数）。
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
                'total' => 1,
                'success' => 1,
                'failed' => 0,
                'blocked' => [],
                'blocked_count' => 0,
                'message_id' => '2509150001',
            ], $data),
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function execute(string $action, array $payload = [], array $config = []): array
    {
        return (new Stay33SmsService)->execute([
            'action' => $action,
            'payload' => $payload,
            'config' => array_merge([
                'uid' => '123456',
                'api_key' => 'mcloud-key',
                'sign_name' => '测试站点',
                'channel' => '',
                'api_endpoint' => 'https://api.mcloud.test/api/v1/sms/send',
                'backup_endpoint' => '',
            ], $config),
        ]);
    }

    public function test_default_templates_cover_reference_package(): void
    {
        $result = $this->execute('sms.templates.defaults');

        $this->assertTrue($result['success']);
        $defaults = $result['data']['defaults'];

        // 参考插件 config/smsTemplate.php 共 20 条
        $this->assertCount(20, $defaults);

        $titles = array_column($defaults, 'title');
        foreach ([
            '验证码', '用户登录', '用户注册', '客户更改密码', '订单创建',
            '产品开通中', '开通成功', '产品暂停通知', '产品解除暂停通知', '产品删除通知',
            '产品升降级', '第一次续费提醒', '第二次续费提醒',
            '逾期付款第一次提醒', '逾期付款第二次提醒', '逾期付款第三次提醒',
            '订单未付款通知', '订单金额修改', '订单支付通知', '充值成功通知',
        ] as $title) {
            $this->assertContains($title, $titles, '缺少参考插件模板：'.$title);
        }
    }

    public function test_default_templates_translate_var_syntax_and_extract_variables(): void
    {
        $defaults = $this->execute('sms.templates.defaults')['data']['defaults'];

        foreach ($defaults as $default) {
            $this->assertStringNotContainsString('@var(', $default['content'], '默认模板仍含未翻译的 @var()：'.$default['title']);
        }

        $verify = $this->findByTitle($defaults, '验证码');
        $this->assertSame(
            '您的验证码为${code}。尊敬的客户，以上验证码5分钟内有效，请注意保密，切勿告知他人。',
            $verify['content']
        );
        $this->assertSame(['code'], $verify['variables']);

        // 参考包中「用户登录」等少数模板本身无变量，其余模板必须有变量清单
        $withVariables = array_filter($defaults, static fn (array $d): bool => str_contains((string) $d['content'], '${'));
        $this->assertNotEmpty($withVariables);
        foreach ($withVariables as $default) {
            $this->assertNotEmpty($default['variables'], '默认模板未提取变量：'.$default['title']);
            foreach ($default['variables'] as $variable) {
                $this->assertStringContainsString('${'.$variable.'}', (string) $default['content']);
            }
        }

        $suspend = $this->findByTitle($defaults, '产品暂停通知');
        $this->assertSame(['product_name', 'product_suspend_reason'], $suspend['variables']);
    }

    public function test_default_templates_carry_reference_business_codes(): void
    {
        // 参考包每行还有 name 业务键（ZJMF 场景码），导入时必须保留
        $defaults = $this->execute('sms.templates.defaults')['data']['defaults'];

        $codes = array_column($defaults, 'code');
        $this->assertCount(20, array_unique($codes), '业务键必须唯一');

        foreach ([
            'code', 'client_login_success', 'client_register_success', 'client_change_password',
            'order_create', 'host_pending', 'host_active', 'host_suspend', 'host_unsuspend',
            'host_terminate', 'host_upgrad', 'host_renewal_first', 'host_renewal_second',
            'host_overdue_first', 'host_overdue_second', 'host_overdue_third',
            'order_overdue', 'admin_order_amount', 'order_pay', 'order_recharge',
        ] as $code) {
            $this->assertContains($code, $codes, '缺少业务键：'.$code);
        }
    }

    public function test_push_translates_var_syntax_and_uses_official_api(): void
    {
        Http::fake([
            'api.mcloud.test/api/v1/sms/templates' => Http::response([
                'code' => 0,
                'data' => ['id' => 8, 'status' => 0],
                'message' => '模板已提交，等待审核',
            ], 200),
        ]);

        // 管理员可能直接粘贴参考插件的 @var() 原文，提审返回必须是平台语法
        $result = $this->execute('sms.templates.push', [
            'title' => '验证码',
            'content' => '您的验证码为@var(code)，@var(min)分钟内有效。',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('8', $result['data']['provider_template_id']);
        // 官方模板提交后进入审核，不再是「提交即过审」
        $this->assertSame('pending', $result['data']['status']);
        $this->assertSame('您的验证码为${code}，${min}分钟内有效。', $result['data']['content']);
        $this->assertSame(['code', 'min'], $result['data']['variables']);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $request->method() === 'POST'
                && ($body['name'] ?? '') === '验证码'
                && ($body['scene'] ?? '') === 'verify'
                && ($body['content'] ?? '') === '您的验证码为${code}，${min}分钟内有效。';
        });
    }

    public function test_template_pull_returns_official_templates(): void
    {
        Http::fake([
            'api.mcloud.test/api/v1/sms/templates' => Http::response([
                'code' => 0,
                'data' => ['list' => [
                    ['id' => 1, 'name' => '验证码', 'content' => '您的验证码是 ${code}', 'status' => 1],
                ]],
            ], 200),
        ]);

        $result = $this->execute('sms.templates.pull');

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['data']['templates']);
        $this->assertSame('1', $result['data']['templates'][0]['provider_template_id']);
        $this->assertSame('approved', $result['data']['templates'][0]['status']);
    }

    public function test_template_pull_reports_upstream_failure(): void
    {
        Http::fake([
            'api.mcloud.test/api/v1/sms/templates' => Http::response(['code' => 1002, 'message' => '鉴权失败'], 200),
        ]);

        $result = $this->execute('sms.templates.pull');

        $this->assertFalse($result['success']);
        $this->assertSame('鉴权失败', $result['message']);
    }

    public function test_template_status_reads_official_state(): void
    {
        Http::fake([
            'api.mcloud.test/api/v1/sms/templates' => Http::response([
                'code' => 0,
                'data' => ['list' => [['id' => 8, 'content' => 'x', 'status' => 2, 'reason' => '含敏感词']]],
            ], 200),
        ]);

        $result = $this->execute('sms.templates.status', ['provider_template_id' => '8']);

        $this->assertTrue($result['success']);
        $this->assertSame('rejected', $result['data']['status']);
        $this->assertSame('含敏感词', $result['data']['reject_reason']);
    }

    public function test_fetch_signs_returns_official_list(): void
    {
        Http::fake([
            'api.mcloud.test/api/v1/sms/signatures' => Http::response([
                'code' => 0,
                'data' => ['list' => [['id' => 1, 'name' => '某某科技', 'status' => 1, 'carrier_ok' => true]]],
            ], 200),
        ]);

        $result = $this->execute('sms.fetch_signs');

        $this->assertTrue($result['success']);
        $this->assertSame('某某科技', $result['data']['signs'][0]['name']);
        $this->assertSame('approved', $result['data']['signs'][0]['status']);
    }

    public function test_verify_code_template_is_returned_by_purpose(): void
    {
        $generic = $this->execute('sms.verify_code_template', ['purpose' => 'generic']);
        $reset = $this->execute('sms.verify_code_template', ['purpose' => 'reset']);
        $bind = $this->execute('sms.verify_code_template', ['purpose' => 'bind_phone']);
        $change = $this->execute('sms.verify_code_template', ['purpose' => 'change_phone']);
        $verifyBound = $this->execute('sms.verify_code_template', ['purpose' => 'verify_bound_phone']);

        foreach ([$generic, $reset, $bind, $change, $verifyBound] as $result) {
            $this->assertTrue($result['success']);
            $this->assertStringContainsString('${code}', $result['data']['template']);
            $this->assertStringContainsString('${min}', $result['data']['template']);
        }

        // 不同场景文案必须可区分，否则同步验证码模板会互相覆盖
        $contents = [$generic, $reset, $bind, $change, $verifyBound];
        $tests = array_map(static fn (array $r): string => $r['data']['template'], $contents);
        $this->assertCount(5, array_unique($tests));
    }

    public function test_unknown_purpose_falls_back_to_generic_template(): void
    {
        $generic = $this->execute('sms.verify_code_template', ['purpose' => 'generic']);
        $unknown = $this->execute('sms.verify_code_template', ['purpose' => 'not-a-purpose']);

        $this->assertSame($generic['data']['template'], $unknown['data']['template']);
    }

    public function test_send_verify_code_routes_through_v10_client(): void
    {
        Http::fake([
            'api.mcloud.test/*' => Http::response(['code' => 0, 'data' => ['id' => 'sms-1']], 200),
        ]);

        $result = $this->execute('sms.send_verify_code', ['phone' => '13800000000', 'code' => '654321']);

        $this->assertTrue($result['success']);
        $this->assertSame('success', $result['data']['status']);
        $this->assertSame('sms-1', $result['data']['request_id']);
    }

    public function test_send_message_routes_through_v10_client(): void
    {
        Http::fake([
            'api.mcloud.test/*' => Http::response($this->successBody(), 200),
        ]);

        $result = $this->execute('sms.send_message', [
            'phone' => '13800000000',
            'template_code' => '100003',
            'content' => '您的服务即将到期',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('100003', $result['data']['template_code']);
        // 签名走官方 signature 参数，正文原样下发
        $this->assertSame('您的服务即将到期', $result['data']['template_params']['content']);
    }

    public function test_test_action_generates_six_digit_code(): void
    {
        Http::fake([
            'api.mcloud.test/*' => Http::response(['code' => 0], 200),
        ]);

        $result = $this->execute('sms.test', ['phone' => '13800000000', 'code' => 'not-a-code']);

        $this->assertTrue($result['success']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $result['data']['template_params']['code']);
    }

    public function test_missing_phone_or_code_fails_without_request(): void
    {
        Http::fake();

        $this->assertFalse($this->execute('sms.send_verify_code', ['phone' => '', 'code' => '123456'])['success']);
        $this->assertFalse($this->execute('sms.send_verify_code', ['phone' => '13800000000', 'code' => ''])['success']);

        Http::assertNothingSent();
    }

    public function test_unknown_action_is_rejected(): void
    {
        $result = $this->execute('sms.unknown_action');

        $this->assertFalse($result['success']);
        $this->assertSame('Unsupported plugin action', $result['message']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $defaults
     * @return array<string, mixed>
     */
    private function findByTitle(array $defaults, string $title): array
    {
        foreach ($defaults as $default) {
            if (($default['title'] ?? '') === $title) {
                return $default;
            }
        }

        $this->fail('未找到默认模板：'.$title);
    }
}
