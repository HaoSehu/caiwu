<?php

declare(strict_types=1);

namespace Tests\Feature;

use Caiwu\Plugins\Captcha\Geetest\Lib\GeetestCaptchaService;
use Caiwu\Plugins\Captcha\Vaptcha\Lib\VaptchaCaptchaService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../../plugins/captcha/vaptcha/lib/VaptchaCaptchaService.php';
require_once __DIR__.'/../../plugins/captcha/geetest/lib/GeetestCaptchaService.php';

/**
 * 人机验证防重放测试（D-04 / D-05）。
 *
 * 两个驱动都从服务端二次校验，但服务端本身不去重：同一份验证结果可以被
 * 反复提交。这里锁定「同一验证结果只能消费一次」的契约。
 */
class CaptchaReplayProtectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_vaptcha_rejects_replayed_token(): void
    {
        Http::fake([
            'v4i.vaptcha.com/*' => Http::response([
                'code' => 0,
                'data' => ['result' => 'success', 'vid' => 'vid-1'],
            ]),
        ]);

        $service = new VaptchaCaptchaService;
        $config = ['vid' => 'vid-1', 'vkey' => 'vkey-1', 'replay_ttl_seconds' => 600];
        $payload = ['token' => 'token-1', 'knock' => 'knock-1', 'dfu' => 'dfu-1', '_client_ip' => '10.0.0.1'];

        $first = $service->execute(['action' => 'captcha.verify', 'payload' => $payload, 'config' => $config]);
        $this->assertTrue($first['success'], '首次校验应通过');

        $second = $service->execute(['action' => 'captcha.verify', 'payload' => $payload, 'config' => $config]);
        $this->assertFalse($second['success'], '重放同一 token 应被拒绝');
        $this->assertSame(false, $second['data']['verified'] ?? null);
    }

    public function test_vaptcha_replay_window_is_not_three_seconds(): void
    {
        // 回归防线：默认窗口曾被写成 3 秒，短于真实下单耗时，等于没有防重放。
        $defaults = require base_path('plugins/captcha/vaptcha/config.php');
        $this->assertSame(600, (int) $defaults['config']['replay_ttl_seconds']['value']);
    }

    public function test_geetest_rejects_replayed_lot_number(): void
    {
        Http::fake([
            'gcaptcha4.geetest.com/*' => Http::response(['result' => 'success']),
        ]);

        $service = new GeetestCaptchaService;
        $config = ['captcha_id' => 'cid-1', 'captcha_key' => 'ckey-1'];
        $payload = [
            'lot_number' => 'lot-1',
            'captcha_output' => 'output-1',
            'pass_token' => 'pass-1',
            'gen_time' => '1700000000',
        ];

        $first = $service->execute(['action' => 'captcha.verify', 'payload' => $payload, 'config' => $config]);
        $this->assertTrue($first['success'], '首次校验应通过');

        $second = $service->execute(['action' => 'captcha.verify', 'payload' => $payload, 'config' => $config]);
        $this->assertFalse($second['success'], '重放同一 lot_number 应被拒绝');
        $this->assertSame(false, $second['data']['verified'] ?? null);
    }
}
