<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Finance\AdminFinanceQueryService;
use App\Services\Finance\OrderV2QueryService;
use App\Services\Order\ClientOrderQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 订单状态对外统一 4 态：筛选选中"已支付"(1) 时一并匹配
 * PROCESSING(2)/COMPLETED(3) 内部子状态，其余状态精确匹配；
 * 客户端 summary 的 paid 计数同样合并三个内部子状态。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class OrderStatusFilterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_finance_order_filter_merges_paid_substatuses(): void
    {
        $keyword = $this->makeOrdersForFilter();

        $service = app(AdminFinanceQueryService::class);

        $this->assertSame(3, $service->paginateOrders(['status' => OrderStatus::PAID, 'keyword' => $keyword])->total());

        foreach ([OrderStatus::PENDING, OrderStatus::PROCESSING, OrderStatus::COMPLETED, OrderStatus::CANCELLED, OrderStatus::REFUNDED] as $status) {
            $this->assertSame(1, $service->paginateOrders(['status' => $status, 'keyword' => $keyword])->total());
        }
    }

    public function test_admin_v2_order_filter_merges_paid_substatuses(): void
    {
        $keyword = $this->makeOrdersForFilter();

        $service = app(OrderV2QueryService::class);

        $this->assertSame(3, $service->paginateAdminOrders(['status' => OrderStatus::PAID, 'keyword' => $keyword])->total());

        foreach ([OrderStatus::PENDING, OrderStatus::PROCESSING, OrderStatus::COMPLETED, OrderStatus::CANCELLED, OrderStatus::REFUNDED] as $status) {
            $this->assertSame(1, $service->paginateAdminOrders(['status' => $status, 'keyword' => $keyword])->total());
        }
    }

    public function test_admin_upgrade_order_filter_merges_paid_substatuses(): void
    {
        $keyword = $this->makeOrdersForFilter('upgrade');

        $service = app(AdminFinanceQueryService::class);

        $this->assertSame(3, $service->paginateUpgradeOrders(['status' => OrderStatus::PAID, 'keyword' => $keyword])->total());
        $this->assertSame(1, $service->paginateUpgradeOrders(['status' => OrderStatus::CANCELLED, 'keyword' => $keyword])->total());
    }

    public function test_client_order_filter_merges_paid_substatuses(): void
    {
        [$userId] = $this->makeOrdersForClient();

        $service = app(ClientOrderQueryService::class);

        $this->assertSame(3, $service->paginate($userId, ['status' => OrderStatus::PAID], [])['total']);

        foreach ([OrderStatus::PENDING, OrderStatus::CANCELLED, OrderStatus::REFUNDED] as $status) {
            $this->assertSame(1, $service->paginate($userId, ['status' => $status], [])['total']);
        }
    }

    public function test_client_summary_merges_paid_substatuses(): void
    {
        [$userId] = $this->makeOrdersForClient();

        $summary = app(ClientOrderQueryService::class)->summary($userId, []);

        $this->assertSame(1, $summary['pending']);
        $this->assertSame(3, $summary['paid']);
        $this->assertSame(1, $summary['cancelled']);
        $this->assertSame(1, $summary['refunded']);
        $this->assertArrayNotHasKey('processing', $summary);
        $this->assertArrayNotHasKey('completed', $summary);
    }

    /**
     * @return array{0: int}
     */
    private function makeOrdersForClient(): array
    {
        $user = $this->makeUser('client');

        foreach (array_keys(OrderStatus::$labels) as $status) {
            $this->makeOrder($user, $status);
        }

        return [(int) $user->id];
    }

    private function makeOrdersForFilter(string $type = 'new'): string
    {
        $user = $this->makeUser('filter');

        foreach (array_keys(OrderStatus::$labels) as $status) {
            $this->makeOrder($user, $status, $type);
        }

        // keyword 命中造数用户唯一邮箱，将管理端查询隔离到本测试数据
        return (string) $user->email;
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeOrder(User $user, int $status, string $type = 'new'): Order
    {
        return Order::query()->create([
            'order_no' => 'OF'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => $type,
            'amount' => 100.00,
            'paid_amount' => $status === OrderStatus::PENDING ? 0 : 100.00,
            'status' => $status,
            'paid_at' => $status === OrderStatus::PENDING ? null : now(),
        ]);
    }
}
