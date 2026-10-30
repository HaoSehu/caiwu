<?php

declare(strict_types=1);

namespace TestsUnit;

use Caiwu\Plugins\Captcha\Vaptcha\Lib\VaptchaCaptchaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/VaptchaCaptchaService.php';

/**
 * VAPTCHA v4 行为验证回归。
 *
 * 关注两点：一是上游返回必须同时满足 code=0、result 为真、vid 与本端一致才算通过，
 * 任一不满足都要落成失败信封；二是 token 的防重放窗口要真的生效。
 */
class VaptchaCaptchaServiceTest extends TestCase
{
    private const CONFIG = [
        'vid' => 'vaptcha-vid',
        'vkey' => 'vaptcha-vkey',
    ];

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'token' => 'token-0001',
            'knock' => 'knock-0001',
            'dfu' => 'dfu-0001',
            'ip' => '203.0.113.9',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function verify(array $payload = [], array $config = self::CONFIG): array
    {
        return (new VaptchaCaptchaService)->execute([
            'action' => 'captcha.verify',
            'payload' => $this->payload($payload),
            'config' => $config,
        ]);
    }

    public function test_config_action_reports_enabled_state(): void
    {
        $result = (new VaptchaCaptchaService)->execute(['action' => 'captcha.config', 'config' => self::CONFIG]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['enabled']);
        $this->assertSame('vaptcha', $result['data']['provider']);
        $this->assertSame('vaptcha-vid', $result['data']['vid']);
    }

    public function test_unknown_action_is_rejected(): void
    {
        $result = (new VaptchaCaptchaService)->execute(['action' => 'captcha.unknown']);

        $this->assertFalse($result['success']);
        $this->assertSame('不支持的人机验证动作', $result['message']);
    }

    public function test_unconfigured_plugin_fails_before_calling_upstream(): void
    {
        Http::fake();

        $result = $this->verify([], ['vid' => '', 'vkey' => '']);

        $this->assertFalse($result['success']);
        $this->assertSame('VAPTCHA 插件配置不完整', $result['message']);
        Http::assertNothingSent();
    }

    public function test_missing_token_or_knock_is_rejected(): void
    {
        Http::fake();

        $noToken = $this->verify(['token' => '', 'vaptcha_token' => '']);
        $this->assertFalse($noToken['success']);
        $this->assertSame('行为验证参数不完整', $noToken['message']);

        $noKnock = $this->verify(['knock' => '', 'vaptcha_knock' => '']);
        $this->assertFalse($noKnock['success']);
        $this->assertSame('行为验证参数不完整', $noKnock['message']);

        Http::assertNothingSent();
    }

    public function test_missing_client_ip_is_rejected(): void
    {
        Http::fake();

        $result = $this->verify(['ip' => '', '_client_ip' => '', 'client_ip' => '']);

        $this->assertFalse($result['success']);
        $this->assertSame('行为验证 IP 缺失，请稍后重试', $result['message']);
        Http::assertNothingSent();
    }

    public function test_success_requires_code_result_and_matching_vid(): void
    {
        Http::fake([
            'v4i.vaptcha.com/*' => Http::response([
                'code' => 0,
                'data' => ['result' => 1, 'vid' => 'vaptcha-vid'],
            ], 200),
        ]);

        $result = $this->verify();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['verified']);
        Http::assertSent(fn (Request $request): bool => $request->data()['vid'] === 'vaptcha-vid'
            && $request->data()['vkey'] === 'vaptcha-vkey'
            && $request->data()['ip'] === '203.0.113.9');
    }

    public function test_non_zero_code_fails_even_when_result_truthy(): void
    {
        Http::fake([
            'v4i.vaptcha.com/*' => Http::response([
                'code' => 1001,
                'data' => ['result' => 1, 'vid' => 'vaptcha-vid'],
            ], 200),
        ]);

        $result = $this->verify();

        $this->assertFalse($result['success']);
        $this->assertFalse($result['data']['verified']);
        $this->assertSame('行为验证未通过，请重试', $result['message']);
    }

    public function test_truthy_result_false_fails(): void
    {
        Http::fake([
            'v4i.vaptcha.com/*' => Http::response([
                'code' => 0,
                'data' => ['result' => 0, 'vid' => 'vaptcha-vid'],
            ], 200),
        ]);

        $result = $this->verify();

        $this->assertFalse($result['success']);
        $this->assertSame('行为验证未通过，请重试', $result['message']);
    }

    public function test_vid_mismatch_fails(): void
    {
        Http::fake([
            'v4i.vaptcha.com/*' => Http::response([
                'code' => 0,
                'data' => ['result' => 'true', 'vid' => 'another-vid'],
            ], 200),
        ]);

        $result = $this->verify();

        $this->assertFalse($result['success']);
        $this->assertSame('行为验证未通过，请重试', $result['message']);
    }

    public function test_absent_vid_in_response_is_tolerated(): void
    {
        Http::fake([
            'v4i.vaptcha.com/*' => Http::response([
                'code' => 0,
                'data' => ['result' => 'success'],
            ], 200),
        ]);

        // 上游未回传 vid 时视为「不提供可核对值」，不作为拒绝理由。
        $this->assertTrue($this->verify()['success']);
    }

    public function test_non_array_body_reports_abnormal_response(): void
    {
        Http::fake([
            'v4i.vaptcha.com/*' => Http::response('not-json', 200, ['Content-Type' => 'text/plain']),
        ]);

        $result = $this->verify();

        $this->assertFalse($result['success']);
        $this->assertSame('行为验证服务响应异常，请稍后重试', $result['message']);
    }

    public function test_upstream_server_error_reports_service_unavailable(): void
    {
        Http::fake([
            'v4i.vaptcha.com/*' => Http::response('boom', 502),
        ]);

        $result = $this->verify();

        $this->assertFalse($result['success']);
        $this->assertSame('行为验证服务暂时不可用，请稍后重试', $result['message']);
    }

    public function test_upstream_exception_is_converted_to_failure_envelope(): void
    {
        Http::fake(fn () => throw new \RuntimeException('connection reset'));

        $result = $this->verify();

        $this->assertFalse($result['success']);
        $this->assertSame('行为验证服务暂时不可用，请稍后重试', $result['message']);
    }

    public function test_same_token_can_only_be_consumed_once(): void
    {
        Http::fake([
            'v4i.vaptcha.com/*' => Http::response([
                'code' => 0,
                'data' => ['result' => 1, 'vid' => 'vaptcha-vid'],
            ], 200),
        ]);

        // 先落到变量再断言：直接写 $this->verify()['success'] 会让 PHPStan 把该调用表达式的
        // 类型收窄成 success=true，下一行同一表达式取回放结果时就会误报 assertFalse 恒真。
        $first = $this->verify();
        $this->assertTrue($first['success']);

        $replay = $this->verify();

        $this->assertFalse($replay['success']);
        $this->assertFalse($replay['data']['verified']);
        $this->assertSame('行为验证已使用，请重新验证', $replay['message']);
    }

    public function test_script_embeds_configured_sdk_url(): void
    {
        $result = (new VaptchaCaptchaService)->execute([
            'action' => 'captcha.script',
            'config' => self::CONFIG + ['sdk_url' => 'https://cdn.example.test/v4.js'],
        ]);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('https://cdn.example.test/v4.js', $result['data']['content']);
        $this->assertStringNotContainsString('__VAPTCHA_SDK_URL__', $result['data']['content'], '配置的 SDK 地址必须整段替换掉占位符');
    }
}
