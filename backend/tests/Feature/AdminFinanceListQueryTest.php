<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\AdminFinanceQueryService;
use App\Services\System\DatabaseStatusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 管理端财务列表查询修复回归（D1A-05 / D1B-05 / D1B-06 / E2-06）：
 * 1. 充值记录默认全渠道可见（原硬编码 ALIPAY 导致易支付等渠道在列表不可见）；
 * 2. 充值列表显式投影，不再逐行拉取 payments.callback_raw（整份网关原始回调 JSON）；
 * 3. 续费/附加配置订单列表显式投影，无消费者的 JSON 快照列不进 SQL；
 * 4. 整库备份排除运行日志表（仅断言 mysqldump 参数构造，不真实执行备份）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminFinanceListQueryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_recharge_list_covers_all_gateways_with_labels(): void
    {
        $user = $this->makeUser();
        $this->makePayment($user, 'alipay');
        $this->makePayment($user, 'yipay');

        // 共用 idc_test 库存在既有数据：以造数用户唯一邮箱作 keyword，隔离到本测试数据。
        $paginator = app(AdminFinanceQueryService::class)->paginateRecharges(['keyword' => $user->email]);

        $this->assertSame(2, $paginator->total());
        $labels = $paginator->getCollection()->pluck('gateway_label', 'gateway_key')->all();
        $this->assertSame('支付宝', $labels['alipay']);
        $this->assertSame('易支付', $labels['yipay']);
    }

    public function test_recharge_list_query_excludes_callback_raw(): void
    {
        $user = $this->makeUser();
        $this->makePayment($user, 'alipay');

        $sqlList = [];
        DB::listen(function ($query) use (&$sqlList): void {
            $sqlList[] = strtolower((string) $query->sql);
        });

        app(AdminFinanceQueryService::class)->paginateRecharges([]);

        $this->assertNotEmpty($sqlList);
        // 主查询与全部关系预取均不得选取 callback_raw。
        foreach ($sqlList as $sql) {
            $this->assertStringNotContainsString('callback_raw', $sql);
        }
        // 主查询必须投影 gateway_key，保证 transform 侧 gatewayKey() 可用；
        // 用 limit 区分分页主查询与 count(*) 聚合。
        $mainQuery = collect($sqlList)->first(
            fn (string $sql): bool => (str_contains($sql, 'from `payments`') || str_contains($sql, 'from "payments"'))
                && str_contains($sql, 'limit')
        );
        $this->assertNotNull($mainQuery);
        $this->assertStringContainsString('gateway_key', $mainQuery);
    }

    public function test_renewal_order_list_query_excludes_discarded_snapshot_columns(): void
    {
        $user = $this->makeUser();
        $this->makeOrder($user, OrderStatus::PAID, OrderType::RENEW);

        $sqlList = [];
        DB::listen(function ($query) use (&$sqlList): void {
            $sqlList[] = strtolower((string) $query->sql);
        });

        // 共用 idc_test 库存在既有数据：以造数用户唯一邮箱作 keyword 隔离。
        $paginator = app(AdminFinanceQueryService::class)->paginateOrders(
            ['keyword' => $user->email],
            20,
            OrderType::RENEW
        );

        $this->assertSame(1, $paginator->total());
        // 无消费者的 JSON 快照列不得进任何 SQL（含关系预取）。
        foreach ($sqlList as $sql) {
            $this->assertStringNotContainsString('coupon_snapshot', $sql);
            $this->assertStringNotContainsString('service_snapshot', $sql);
            $this->assertStringNotContainsString('config_pricing_snapshot', $sql);
        }
        // 产品名/路径解析真实消费的快照列必须保留，列表展示不回退；
        // 用 limit 区分分页主查询与 count(*) 聚合。
        $mainQuery = collect($sqlList)->first(
            fn (string $sql): bool => (str_contains($sql, 'from `orders`') || str_contains($sql, 'from "orders"'))
                && str_contains($sql, 'limit')
        );
        $this->assertNotNull($mainQuery);
        $this->assertStringContainsString('config_snapshot', $mainQuery);
        $this->assertStringContainsString('product_spec_snapshot', $mainQuery);
    }

    public function test_upgrade_order_list_keeps_pricing_snapshot_meta(): void
    {
        $user = $this->makeUser();
        $this->makeOrder($user, OrderStatus::PAID, OrderType::UPGRADE, [
            'meta' => ['kind' => 'traffic_package', 'target_label' => '流量包 100G', 'mode' => 'renew'],
        ]);
        // 续费单不应混入附加配置列表。
        $this->makeOrder($user, OrderStatus::PAID, OrderType::RENEW);

        $service = app(AdminFinanceQueryService::class);
        // 共用 idc_test 库存在既有数据：以造数用户唯一邮箱作 keyword 隔离。
        $paginator = $service->paginateUpgradeOrders(['keyword' => $user->email]);

        $this->assertSame(1, $paginator->total());
        $item = $paginator->getCollection()->first();
        $this->assertSame('traffic_package', $item['upgrade_kind']);
        $this->assertSame('流量包', $item['upgrade_kind_label']);
        $this->assertSame('流量包 100G', $item['upgrade_target_label']);
        $this->assertSame('renew', $item['upgrade_mode']);

        // kind 筛选走 SQL 层 JSON 提取，与列投影解耦，仍需正常工作。
        $this->assertSame(1, $service->paginateUpgradeOrders([
            'keyword' => $user->email,
            'upgrade_kind' => 'traffic_package',
        ])->total());
        $this->assertSame(0, $service->paginateUpgradeOrders([
            'keyword' => $user->email,
            'upgrade_kind' => 'unknown',
        ])->total());
    }

    public function test_backup_excludes_high_volume_log_tables(): void
    {
        $service = app(DatabaseStatusService::class);
        $method = new ReflectionMethod(DatabaseStatusService::class, 'backupIgnoreTableOptions');

        $options = $method->invoke($service, 'idc_test');

        $this->assertSame([
            '--ignore-table=idc_test.activity_logs',
            '--ignore-table=idc_test.integration_plugin_runtime_logs',
            '--ignore-table=idc_test.gateway_logs',
            '--ignore-table=idc_test.operation_logs',
            '--ignore-table=idc_test.schedule_run_logs',
            '--ignore-table=idc_test.schedule_task_runs',
        ], $options);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'finance-list-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'finance-list-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makePayment(User $user, string $gateway): Payment
    {
        return Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => $user->id,
            'gateway' => $gateway,
            'trade_no' => 'TRADE'.date('YmdHis').mt_rand(100000, 999999),
            'amount' => 50.00,
            'currency' => 'CNY',
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
        ]);
    }

    private function makeOrder(User $user, int $status, string $type, ?array $configPricingSnapshot = null): Order
    {
        return Order::query()->create([
            'order_no' => 'OF'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => $type,
            'amount' => 100.00,
            'paid_amount' => $status === OrderStatus::PENDING ? 0 : 100.00,
            'status' => $status,
            'paid_at' => $status === OrderStatus::PENDING ? null : now(),
            'config_pricing_snapshot' => $configPricingSnapshot,
        ]);
    }
}
