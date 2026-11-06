<?php

declare(strict_types=1);

namespace Tests\Unit;

use Caiwu\Plugins\Gateways\YiPay\Lib\YiPayClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/YiPayClient.php';

/**
 * 易支付预下单 device 口径回归。
 *
 * 背景（2026-11 线上问题）：微信通道固定上报 device=pc 时，网关只下发
 * `pay/jspay/…` 公众号收银台；该页面在任何浏览器都会 302 到微信 OAuth，
 * 微信外打开会被微信拒绝并提示 invalid_source。修复后微信通道按请求 UA 上报
 * wechat / mobile / pc，支付宝通道维持 pc 不变。
 */
class YiPayClientPrecreateDeviceTest extends TestCase
{
    private const CONFIG = [
        'merchant_id' => '10001',
        'merchant_key' => 'test-merchant-key',
        'sign_type' => 'MD5',
        'api_endpoint' => 'https://pay.example.com/',
        'payment_types' => ['alipay', 'wxpay'],
    ];

    private const WECHAT_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.49 NetType/WIFI';

    private const IOS_SAFARI_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const DESKTOP_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'pay.example.com/mapi.php' => Http::response([
                'code' => 1,
                'trade_no' => 'YIPAY-TRADE-1',
                'qrcode' => 'https://pay.example.com/pay/jspay/YIPAY-TRADE-1/',
            ]),
        ]);
    }

    private function precreate(string $paymentType, string $userAgent): void
    {
        $this->app['request']->headers->set('User-Agent', $userAgent);

        (new YiPayClient(self::CONFIG))->precreate('PAY-TEST-1', 20.0, '账户充值', $paymentType);
    }

    /**
     * @return array<string, mixed>
     */
    private function sentPayload(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = [];

        Http::assertSent(function (Request $request) use (&$payload): bool {
            $payload = $request->data();

            return true;
        });

        return $payload;
    }

    public function test_wechat_channel_reports_wechat_inside_wechat_browser(): void
    {
        $this->precreate('wxpay', self::WECHAT_UA);

        $this->assertSame('wechat', $this->sentPayload()['device'] ?? null);
    }

    public function test_wechat_channel_reports_mobile_outside_wechat_browser(): void
    {
        $this->precreate('wxpay', self::IOS_SAFARI_UA);

        $this->assertSame('mobile', $this->sentPayload()['device'] ?? null);
    }

    public function test_wechat_channel_reports_pc_on_desktop(): void
    {
        $this->precreate('wxpay', self::DESKTOP_UA);

        $this->assertSame('pc', $this->sentPayload()['device'] ?? null);
    }

    public function test_alipay_channel_keeps_pc_device_on_mobile(): void
    {
        // 支付宝通道现有线上流程正常，本轮修复不扩大改动面：无论 UA 都维持 pc。
        $this->precreate('alipay', self::IOS_SAFARI_UA);

        $this->assertSame('pc', $this->sentPayload()['device'] ?? null);
    }
}
