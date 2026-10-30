<?php

declare(strict_types=1);

namespace TestsUnit;

use Caiwu\Plugins\Captcha\Geetest\Lib\GeetestCaptchaService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/GeetestCaptchaService.php';

/**
 * GeeTest v4 行为验证回归。
 *
 * 上游 validate 接口本身不对 lot_number 去重，一次性通过的批次号若能被反复消费，
 * 就等于把人机验证变成可重放的通行证；这里锁住「同一 lot_number 只成功一次」的契约。
 */
class GeetestCaptchaServiceTest extends TestCase
{
    private const CONFIG = [
        'captcha_id' => 'geetest-captcha-id',
        'captcha_key' => 'geetest-captcha-key',
    ];

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'lot_number' => 'lot-0001',
            'captcha_output' => 'output-0001',
            'pass_token' => 'pass-0001',
            'gen_time' => '1729000000',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function verify(array $payload = [], array $config = self::CONFIG): array
    {
        return (new GeetestCaptchaService)->execute([
            'action' => 'captcha.verify',
            'payload' => $this->payload($payload),
            'config' => $config,
        ]);
    }

    public function test_config_action_reports_enabled_state(): void
    {
        $service = new GeetestCaptchaService;

        $configured = $service->execute(['action' => 'captcha.config', 'config' => self::CONFIG]);
        $this->assertTrue($configured['success']);
        $this->assertTrue($configured['data']['enabled']);
        $this->assertSame('geetest', $configured['data']['provider']);
        $this->assertSame('geetest-captcha-id', $configured['data']['captcha_id']);

        $blank = $service->execute(['action' => 'captcha.config', 'config' => ['captcha_id' => 'x', 'captcha_key' => '']]);
        $this->assertFalse($blank['data']['enabled']);
        $this->assertSame('x', $blank['data']['captcha_id']);
    }

    public function test_unknown_action_is_rejected(): void
    {
        $result = (new GeetestCaptchaService)->execute(['action' => 'captcha.unknown']);

        $this->assertFalse($result['success']);
        $this->assertSame('不支持的人机验证动作', $result['message']);
    }

    public function test_unconfigured_plugin_fails_before_calling_upstream(): void
    {
        Http::fake();

        $result = $this->verify([], ['captcha_id' => '', 'captcha_key' => '']);

        $this->assertFalse($result['success']);
        $this->assertSame('行为验证插件配置不完整', $result['message']);
        Http::assertNothingSent();
    }

    public function test_incomplete_payload_fails_before_calling_upstream(): void
    {
        Http::fake();

        foreach (['lot_number', 'captcha_output', 'pass_token', 'gen_time'] as $field) {
            $result = $this->verify([$field => '']);
            $this->assertFalse($result['success'], $field.' 为空时必须拒绝');
            $this->assertSame('行为验证参数不完整', $result['message']);
        }

        Http::assertNothingSent();
    }

    public function test_successful_validation_sends_hmac_sign_token(): void
    {
        Http::fake([
            'gcaptcha4.geetest.com/*' => Http::response(['result' => 'success', 'reason' => ''], 200),
        ]);

        $result = $this->verify();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['verified']);

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return str_contains($request->url(), '/validate')
                && $body['lot_number'] === 'lot-0001'
                && $body['captcha_id'] === 'geetest-captcha-id'
                && $body['sign_token'] === hash_hmac('sha256', 'lot-0001', 'geetest-captcha-key');
        });
    }

    public function test_failed_validation_reports_retryable_failure(): void
    {
        Http::fake([
            'gcaptcha4.geetest.com/*' => Http::response(['result' => 'fail', 'reason' => 'verify fail'], 200),
        ]);

        $result = $this->verify();

        $this->assertFalse($result['success']);
        $this->assertFalse($result['data']['verified']);
        $this->assertSame('行为验证未通过，请重试', $result['message']);
    }

    public function test_upstream_server_error_reports_service_unavailable(): void
    {
        Http::fake([
            'gcaptcha4.geetest.com/*' => Http::response('upstream down', 500),
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

    public function test_same_lot_number_can_only_be_consumed_once(): void
    {
        Http::fake([
            'gcaptcha4.geetest.com/*' => Http::response(['result' => 'success', 'reason' => ''], 200),
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

    public function test_script_is_fetched_once_and_served_from_cache(): void
    {
        Http::fake([
            'static.geetest.com/*' => Http::response('window.initGeetest4 = function () {};', 200),
        ]);

        $service = new GeetestCaptchaService;

        $first = $service->execute(['action' => 'captcha.script']);
        $this->assertTrue($first['success']);
        $this->assertSame('window.initGeetest4 = function () {};', $first['data']['content']);

        $second = $service->execute(['action' => 'captcha.script']);
        $this->assertTrue($second['success']);

        Http::assertSentCount(1);
    }

    public function test_script_fetch_failure_is_a_failure_envelope(): void
    {
        Http::fake([
            'static.geetest.com/*' => Http::response('', 404),
        ]);

        $result = (new GeetestCaptchaService)->execute(['action' => 'captcha.script']);

        $this->assertFalse($result['success']);
        $this->assertSame('行为验证脚本暂时不可用，请稍后重试', $result['message']);
    }
}
