<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Models\UserAccount;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfBillingRestoreService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Zjmf 账单恢复的目标库守卫：非空校验已移入恢复事务内（缩小「计数后、写入前」
 * 并发窗口），dry-run 保持只读预检；--force 语义保持「显式确认后物理删除重插」。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ZjmfBillingRestoreServiceTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> */
    private array $dumpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->dumpFiles as $dumpFile) {
            @unlink($dumpFile);
        }
        $this->dumpFiles = [];

        parent::tearDown();
    }

    public function test_dry_run_reports_existing_counts_without_writing(): void
    {
        $user = $this->makeUser('zrestore-dry');
        $existing = $this->makeInvoice($user, 'EXIST-DRY-1');
        // 测试库可能带历史数据，existing 计数按造数后的总量做相对断言
        $before = Invoice::query()->count();
        $dump = $this->makeDumpFile((int) $user->id);

        $summary = (new ZjmfBillingRestoreService)->restoreFromSqlDump($dump, dryRun: true);

        $this->assertTrue((bool) ($summary['dry_run'] ?? false));
        $this->assertSame($before, (int) ($summary['existing_invoices'] ?? 0));
        $this->assertSame(1, (int) ($summary['invoices'] ?? 0));
        $this->assertFalse((bool) ($summary['overwrite_forced'] ?? false));
        // dry-run 只读：既有账单未被删除，dump 账单未写入
        $this->assertNotNull($existing->fresh());
        $this->assertSame($before, Invoice::query()->count());
        $this->assertSame(0, Invoice::query()->where('invoice_no', 'ZJMF-501')->count());
    }

    public function test_restore_without_force_throws_and_keeps_existing_data(): void
    {
        $user = $this->makeUser('zrestore-guard');
        $existing = $this->makeInvoice($user, 'EXIST-GUARD-1');
        $before = Invoice::query()->count();
        $dump = $this->makeDumpFile((int) $user->id);

        $thrown = false;

        try {
            (new ZjmfBillingRestoreService)->restoreFromSqlDump($dump);
        } catch (RuntimeException $exception) {
            $thrown = true;
            $this->assertStringContainsString('已有数据', $exception->getMessage());
        }

        $this->assertTrue($thrown, '目标表非空且未指定 --force 时必须拒绝恢复');
        $this->assertSame($before, Invoice::query()->count(), '被拒绝的恢复不得改动既有账单');
        $this->assertNotNull($existing->fresh());
    }

    public function test_force_overwrites_target_tables_inside_transaction(): void
    {
        $user = $this->makeUser('zrestore-force');
        $this->makeAccount($user->id);
        $existing = $this->makeInvoice($user, 'EXIST-FORCE-1');
        $dump = $this->makeDumpFile((int) $user->id);

        $summary = null;

        try {
            $summary = (new ZjmfBillingRestoreService)->restoreFromSqlDump($dump, forceOverwrite: true);
        } catch (QueryException $exception) {
            // 工具声明要求在「空库或隔离库」执行；本机测试库可能带被 payments(RESTRICT)
            // 引用的历史账单，全表删除不可行。此时只需验证恢复事务回滚后既有数据无损，
            // force 成功路径留给干净隔离库（CI 迁移库）覆盖。
            $this->assertStringContainsString('Integrity constraint violation', $exception->getMessage());
            $this->assertNotNull($existing->fresh(), '恢复事务回滚后既有账单必须无损');

            $this->markTestSkipped('测试库非隔离库（历史账单被 payments 引用），跳过 force 成功路径断言');
        }

        $this->assertNotNull($summary);
        $this->assertTrue((bool) ($summary['overwrite_forced'] ?? false));
        $this->assertSame(1, (int) ($summary['invoices'] ?? 0));
        // 既有账单被物理删除，dump 账单按原 ID/单号重插
        $this->assertNull($existing->fresh());
        $restored = Invoice::query()->whereKey(501)->first();
        $this->assertNotNull($restored);
        $this->assertSame('ZJMF-501', (string) $restored->invoice_no);
        $this->assertSame((int) $user->id, (int) $restored->user_id);
        $this->assertSame('88.00', (string) $restored->amount);
        // 客户余额按 dump 的 shd_clients 快照覆盖
        $this->assertSame(
            '20.00',
            (string) DB::table('user_accounts')->where('user_id', $user->id)->value('cash_balance')
        );
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
        ]);
    }

    private function makeAccount(int $userId): void
    {
        UserAccount::query()->create([
            'user_id' => $userId,
            'cash_balance' => '0.00',
        ]);
    }

    private function makeInvoice(User $user, string $invoiceNo): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => $invoiceNo,
            'user_id' => (int) $user->id,
            'type' => 'manual',
            'amount' => '10.00',
            'paid_amount' => '0.00',
            'status' => 0,
        ]);
    }

    /**
     * 生成最小可解析的上游 dump：一条 shd_clients + 一条已支付 shd_invoices。
     */
    private function makeDumpFile(int $userId): string
    {
        $clientRow = '(3, 0, 0, 0, 1759449600, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '
            ."'20.00')";
        $invoiceRow = "(501, {$userId}, 'ZJMF-501', 1759449600, 1759449600, 1759968000, 1759449600, NULL, '88.00', '0.00', NULL, NULL, '88.00', NULL, NULL, 'Paid', NULL, NULL, 0, NULL, 'product', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0)";

        $dump = "INSERT INTO `shd_clients` VALUES {$clientRow};\n"
            ."INSERT INTO `shd_invoices` VALUES {$invoiceRow};\n";

        $path = sys_get_temp_dir().'/zjmf-restore-test-'.uniqid('', true).'.sql';
        file_put_contents($path, $dump);
        $this->dumpFiles[] = $path;

        return $path;
    }
}
