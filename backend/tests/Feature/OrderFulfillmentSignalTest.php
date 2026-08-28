<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Service;
use App\Services\ClientServiceConsole\ServiceTrafficPackageService;
use App\Services\ClientServiceConsole\ServiceUpgradeService;
use App\Services\Provisioning\ServiceRenewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 订单状态收敛为 4 态后，履约完成判定与幂等边界改由 service.provision_data 承担：
 * - 升级：isUpgradeOrderFulfilled（本订单记录 + last_upgraded_at 非空 + upgrade_error 为空）
 * - 流量包/续费幂等：last_upgrade_order_id+kind / last_renew_order_id 精确匹配
 * 本测试直接构造内存 Order/Service 验证判定函数，不落库。
 */
class OrderFulfillmentSignalTest extends TestCase
{
    use DatabaseTransactions;

    public function test_upgrade_fulfilled_with_success_signal(): void
    {
        $order = $this->memoryOrder(1001);
        $service = $this->memoryService([
            'last_upgrade_order_id' => 1001,
            'last_upgraded_at' => '2026-08-29 10:00:00',
            'upgrade_error' => null,
        ]);

        $this->assertTrue(app(ServiceUpgradeService::class)->isUpgradeOrderFulfilled($order, $service));
    }

    public function test_upgrade_not_fulfilled_when_error_present(): void
    {
        $order = $this->memoryOrder(1001);
        $service = $this->memoryService([
            'last_upgrade_order_id' => 1001,
            'last_upgraded_at' => '2026-08-29 10:00:00',
            'upgrade_error' => '上游购买失败',
        ]);

        $this->assertFalse(app(ServiceUpgradeService::class)->isUpgradeOrderFulfilled($order, $service));
    }

    public function test_upgrade_not_fulfilled_for_other_order(): void
    {
        $order = $this->memoryOrder(1001);
        $service = $this->memoryService([
            'last_upgrade_order_id' => 1002,
            'last_upgraded_at' => '2026-08-29 10:00:00',
            'upgrade_error' => null,
        ]);

        $this->assertFalse(app(ServiceUpgradeService::class)->isUpgradeOrderFulfilled($order, $service));
    }

    public function test_upgrade_not_fulfilled_when_never_upgraded(): void
    {
        $order = $this->memoryOrder(1001);
        $service = $this->memoryService([]);

        $this->assertFalse(app(ServiceUpgradeService::class)->isUpgradeOrderFulfilled($order, $service));
    }

    public function test_traffic_package_idempotency_matches_order_and_kind(): void
    {
        $order = $this->memoryOrder(2001);
        $service = $this->memoryService([
            'last_upgrade_order_id' => 2001,
            'last_upgrade_kind' => 'traffic_package',
        ]);

        $this->assertTrue($this->invokeTrafficPackageIdempotency($order, $service));
    }

    public function test_traffic_package_idempotency_rejects_other_order(): void
    {
        $order = $this->memoryOrder(2001);
        $service = $this->memoryService([
            'last_upgrade_order_id' => 2002,
            'last_upgrade_kind' => 'traffic_package',
        ]);

        $this->assertFalse($this->invokeTrafficPackageIdempotency($order, $service));
    }

    public function test_renew_idempotency_matches_order(): void
    {
        $order = $this->memoryOrder(3001);
        $service = $this->memoryService([
            'last_renew_order_id' => 3001,
        ]);

        $this->assertTrue($this->invokeRenewIdempotency($order, $service));
    }

    public function test_renew_idempotency_rejects_other_order(): void
    {
        $order = $this->memoryOrder(3001);
        $service = $this->memoryService([
            'last_renew_order_id' => 3002,
        ]);

        $this->assertFalse($this->invokeRenewIdempotency($order, $service));
    }

    private function memoryOrder(int $id): Order
    {
        $order = new Order;

        $order->setAttribute('id', $id);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $provisionData
     */
    private function memoryService(array $provisionData): Service
    {
        $service = new Service;

        $service->setAttribute('provision_data', $provisionData);

        return $service;
    }

    private function invokeTrafficPackageIdempotency(Order $order, Service $service): bool
    {
        $method = new ReflectionMethod(ServiceTrafficPackageService::class, 'isTrafficPackageOrderAlreadyCompleted');
        $method->setAccessible(true);

        return (bool) $method->invokeArgs(app(ServiceTrafficPackageService::class), [$order, $service]);
    }

    private function invokeRenewIdempotency(Order $order, Service $service): bool
    {
        $method = new ReflectionMethod(ServiceRenewService::class, 'isRenewOrderAlreadyCompleted');
        $method->setAccessible(true);

        return (bool) $method->invokeArgs(app(ServiceRenewService::class), [$order, $service]);
    }
}
