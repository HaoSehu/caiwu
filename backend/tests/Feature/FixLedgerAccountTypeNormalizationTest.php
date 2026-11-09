<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\System\LedgerConsistencyCheck;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * M-7 / P1-9 回归：finance_ledger_entries.account_type 遗留值 'cash_balance' 归一为 'cash'。
 *
 * 缺陷背景：旧系统导入段写入了 527 行 account_type='cash_balance'（实库统计），
 * 所有按 'cash' 过滤的查询（用户端流水、管理端台账、重建聚合、一致性核对）都
 * 漏掉这批存量行。新增迁移 2026_11_09_000001 幂等归一；LedgerConsistencyCheck 的
 * 现金合计在迁移前后都把两种命名计入。
 *
 * 不用 DatabaseTransactions：迁移 up() 会建回退记录表（DDL），MySQL 对 DDL 隐式
 * 提交，事务回滚兜不住。改为手工登记并清理本用例写入的行；down 用例结束若记录表
 * 消失（测试库此前已应用过该迁移），重跑 up() 恢复共享测试库形态。
 */
class FixLedgerAccountTypeNormalizationTest extends TestCase
{
    private const TRACKING_TABLE = 'finance_ledger_account_type_normalizations';

    /** @var list<int> 本用例插入的台账行 id */
    private array $insertedLedgerIds = [];

    /** @var list<int> 本用例插入的用户 id */
    private array $insertedUserIds = [];

    private bool $trackingTablePreexisting = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trackingTablePreexisting = Schema::hasTable(self::TRACKING_TABLE);
    }

    protected function tearDown(): void
    {
        if ($this->insertedLedgerIds !== []) {
            DB::table('finance_ledger_entries')->whereIn('id', $this->insertedLedgerIds)->delete();

            if (Schema::hasTable(self::TRACKING_TABLE)) {
                DB::table(self::TRACKING_TABLE)->whereIn('entry_id', $this->insertedLedgerIds)->delete();
            }
        }

        if ($this->trackingTablePreexisting && ! Schema::hasTable(self::TRACKING_TABLE)) {
            // down() 用例移除了记录表并把存量归一过的行还原成 cash_balance：重跑 up()
            // 把共享测试库恢复成「迁移已执行」的形态（重新归一 + 重建记录表）。
            $this->migration()->up();
        } elseif (! $this->trackingTablePreexisting) {
            // 迁移尚未应用到测试库：移除本用例创建的记录表，恢复执行前的结构。
            Schema::dropIfExists(self::TRACKING_TABLE);
        }

        foreach ($this->insertedUserIds as $userId) {
            DB::table('finance_accounts')->where('user_id', $userId)->delete();
            DB::table('users')->where('id', $userId)->delete();
        }

        parent::tearDown();
    }

    #[Test]
    public function up_normalizes_legacy_cash_balance_rows_into_cash_queries(): void
    {
        $user = $this->makeUser();
        $legacyId = $this->insertLedgerRow($user, 'cash_balance', '30.00', '2026-03-01 00:00:00');
        $cashId = $this->insertLedgerRow($user, 'cash', '20.00', '2026-09-01 00:00:00');

        $this->migration()->up();

        $this->assertSame(
            'cash',
            DB::table('finance_ledger_entries')->where('id', $legacyId)->value('account_type'),
            '归一后原 cash_balance 行必须改为 cash',
        );
        $this->assertSame(
            'cash',
            DB::table('finance_ledger_entries')->where('id', $cashId)->value('account_type'),
            '正常 cash 行不得被改动',
        );

        // 修复验收口径：只按 account_type='cash' 过滤的查询必须能命中原来的遗留行
        $this->assertSame(
            1,
            DB::table('finance_ledger_entries')
                ->where('user_id', (int) $user->id)
                ->where('account_type', 'cash')
                ->where('id', $legacyId)
                ->count(),
            '迁移后 cash 查询必须命中原 cash_balance 行（用户端/管理端台账可见性修复）',
        );
    }

    #[Test]
    public function up_is_idempotent_and_down_restores_only_normalized_rows(): void
    {
        $user = $this->makeUser();
        $legacyId = $this->insertLedgerRow($user, 'cash_balance', '30.00', '2026-03-01 00:00:00');
        $cashId = $this->insertLedgerRow($user, 'cash', '20.00', '2026-09-01 00:00:00');

        $migration = $this->migration();
        $migration->up();
        $migration->up(); // 重复执行不得报错、不得二次改动

        $this->assertSame('cash', DB::table('finance_ledger_entries')->where('id', $legacyId)->value('account_type'));

        $migration->down();

        $this->assertSame(
            'cash_balance',
            DB::table('finance_ledger_entries')->where('id', $legacyId)->value('account_type'),
            'down 必须按回退记录精确还原被归一的行',
        );
        $this->assertSame(
            'cash',
            DB::table('finance_ledger_entries')->where('id', $cashId)->value('account_type'),
            'down 不得把正常 cash 行改成 cash_balance（只回退记录内的行）',
        );
    }

    #[Test]
    public function cash_balance_sum_check_counts_both_legacy_and_current_naming(): void
    {
        $user = $this->makeUser();

        DB::table('finance_accounts')->insert([
            'user_id' => (int) $user->id,
            'currency' => 'CNY',
            'available_amount' => '50.00',
            'frozen_amount' => '0.00',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 两个命名合计 50.00 与账户余额一致；若只按 'cash' 汇总（20.00）会误报差异。
        $this->insertLedgerRow($user, 'cash_balance', '30.00', '2026-03-01 00:00:00');
        $this->insertLedgerRow($user, 'cash', '20.00', '2026-09-01 00:00:00');

        $result = app(LedgerConsistencyCheck::class)->cashBalanceSumMismatches(100000);

        $mismatchedForUser = array_filter(
            $result['samples'],
            static fn (array $sample): bool => (int) $sample['user_id'] === (int) $user->id,
        );

        $this->assertSame(
            [],
            array_values($mismatchedForUser),
            'cash 合计必须同时计入 cash_balance 遗留行，否则该用户被误报账实不符',
        );
    }

    private function migration(): object
    {
        /** @var object $migration */
        $migration = require base_path('database/migrations/2026_11_09_000001_normalize_ledger_account_type.php');

        return $migration;
    }

    private function makeUser(): User
    {
        $user = User::query()->create([
            'email' => 'acct-type-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'acct-type-tester',
            'total_sales_amount' => 0,
        ]);

        $this->insertedUserIds[] = (int) $user->id;

        return $user;
    }

    private function insertLedgerRow(User $user, string $accountType, string $changeAmount, string $createdAt): int
    {
        $id = (int) DB::table('finance_ledger_entries')->insertGetId([
            'entry_no' => 'LGTEST'.strtoupper(bin2hex(random_bytes(8))),
            'account_id' => null,
            'user_id' => (int) $user->id,
            'account_type' => $accountType,
            'event_type' => 'recharge',
            'change_amount' => $changeAmount,
            'balance_after' => $changeAmount,
            'currency' => 'CNY',
            'source_type' => 'test_seed',
            'source_id' => null,
            'origin_type' => null,
            'origin_id' => null,
            'operator_type' => 'system',
            'operator_id' => null,
            'operator_name' => 'test',
            'remark' => 'account_type 归一迁移用例',
            'trace_id' => null,
            'created_at' => $createdAt,
        ]);

        $this->insertedLedgerIds[] = $id;

        return $id;
    }
}
