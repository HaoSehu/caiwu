<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 维护模式（php artisan down）下的端点行为：
 * 业务端点返回 503（不能被兜底渲染成 500）；支付回调路由豁免维护模式，
 * 必须仍能到达路由层（否则网关重试耗尽、回调永久丢失）。
 */
class MaintenanceModeCallbackTest extends TestCase
{
    use DatabaseTransactions;

    public function test_business_endpoint_returns_503_during_maintenance(): void
    {
        $this->app->maintenanceMode()->activate([]);

        try {
            $response = $this->getJson('/api/v2/client/invoices');

            // 关键：必须是 503，不能被兜底 renderer 吞成 5000/500。
            $this->assertSame(503, $response->status());
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }
    }

    public function test_payment_callback_routes_bypass_maintenance(): void
    {
        $this->app->maintenanceMode()->activate([]);

        try {
            // 未注册的网关：若被维护模式拦住会是 503；穿过维护模式后由路由判定为 404。
            $unknown = $this->postJson('/api/v2/client/payment/notify/zz-not-exist', []);
            $this->assertSame(404, $unknown->status());

            // 支付宝回调：必须穿过维护模式进入控制器（随后被网关签名校验拦下，401），绝不能是 503。
            $alipay = $this->postJson('/api/v2/client/payment/alipay/notify', []);
            $this->assertNotSame(503, $alipay->status());
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }
    }
}
