<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * M-74 回归：normalize_renew_order_amount_to_payable 的「无账单」分支必须可重放。
 *
 * 缺陷背景：该迁移第二条 UPDATE 没有任何「已订正判定」，注释自认「不可重复执行」。
 * 项目 T+7 采用 rollback(no-op)+migrate 的迁移重放模式（000015/000016 同款），
 * 恢复演练/环境重建会重放此迁移——无账单的带折扣续费订单 amount 会被二次扣减
 * （可能变负），reconcile 按错误金额补单。
 *
 * 修复：以订单建单时写入快照的目录价（config_snapshot.local_renew_amount /
 * config_pricing_snapshot.base_amount）作判定锚，只有 amount 仍等于目录价才扣减；
 * 无快照锚点的历史行保守跳过（首轮执行时已订正过的环境无损失）。
 *
 * 迁移只做 UPDATE（无 DDL），DatabaseTransactions 可完整回滚，不会污染共享测试库。
 */
class FixRenewOrderAmountIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function replay_does_not_double_deduct_order_without_invoice(): void
    {
        $user = $this->makeUser();
        $orderId = $this->makeRenewOrder($user, [
            'amount' => '100.00',
            'discount' => '10.00',
            'member_discount_amount' => '5.00',
            'config_snapshot' => json_encode([
                'renew_service_id' => 1,
                'local_renew_amount' => '100.00',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $migration = $this->migration();

        $migration->up();
        $this->assertSame(
            '85.00',
            (string) DB::table('orders')->where('id', $orderId)->value('amount'),
            '首轮订正：amount 应扣减券减免与会员折扣（100 − 10 − 5）',
        );

        // 重放（rollback no-op + migrate 的等价动作）
        $migration->up();
        $this->assertSame(
            '85.00',
            (string) DB::table('orders')->where('id', $orderId)->value('amount'),
            '重放不得二次扣减：修复前会变成 70.00（M-74）',
        );
    }

    #[Test]
    public function replay_uses_config_pricing_snapshot_anchor_as_well(): void
    {
        $user = $this->makeUser();
        $orderId = $this->makeRenewOrder($user, [
            'amount' => '200.00',
            'discount' => '20.00',
            'member_discount_amount' => '0.00',
            'config_snapshot' => json_encode(['renew_service_id' => 2], JSON_UNESCAPED_UNICODE),
            'config_pricing_snapshot' => json_encode([
                'base_amount' => '200.00',
                'meta' => ['kind' => 'renew', 'local_renew_amount' => '200.00'],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $migration = $this->migration();

        $migration->up();
        $migration->up();

        $this->assertSame(
            '180.00',
            (string) DB::table('orders')->where('id', $orderId)->value('amount'),
            '以 config_pricing_snapshot.base_amount 作判定锚时重放同样不得二次扣减',
        );
    }

    #[Test]
    public function order_without_snapshot_anchor_is_left_untouched_on_replay(): void
    {
        $user = $this->makeUser();
        // 无快照锚点（快照列是 NULL）：无法区分目录价/应付价，迁移必须保守跳过。
        $orderId = $this->makeRenewOrder($user, [
            'amount' => '50.00',
            'discount' => '5.00',
            'member_discount_amount' => '0.00',
            'config_snapshot' => null,
            'config_pricing_snapshot' => null,
        ]);

        $this->migration()->up();

        $this->assertSame(
            '50.00',
            (string) DB::table('orders')->where('id', $orderId)->value('amount'),
            '无快照锚点的行必须保守跳过，绝不盲目扣减（否则无法保证重放安全）',
        );
    }

    private function migration(): object
    {
        /** @var object $migration */
        $migration = require base_path('database/migrations/2026_10_05_000001_normalize_renew_order_amount_to_payable.php');

        return $migration;
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'renew-idem-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'renew-idem-tester',
            'total_sales_amount' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeRenewOrder(User $user, array $overrides): int
    {
        return (int) Order::query()->create(array_merge([
            'order_no' => 'RN'.strtoupper(bin2hex(random_bytes(8))),
            'projection_type' => Order::PROJECTION_TYPE_PROVISIONING,
            'user_id' => (int) $user->id,
            'type' => 'renew',
            'amount' => '0.00',
            'discount' => '0.00',
            'member_discount_amount' => '0.00',
            'paid_amount' => 0,
            'quantity' => 1,
            'status' => 0,
        ], $overrides))->id;
    }
}
