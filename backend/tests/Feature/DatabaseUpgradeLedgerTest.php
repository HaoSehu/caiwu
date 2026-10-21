<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\System\DatabaseUpgradeService;
use App\Support\Upgrade\BaselineInspector;
use App\Support\Upgrade\UpgradeLedger;
use App\Support\Upgrade\UpgradeLock;
use App\Support\Upgrade\VersionManifest;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * 升级账本与锁的运行时行为。
 *
 * 覆盖：账本表存在性判断、采纳写入、版本推进、失败不推进版本、锁互斥。
 */
class DatabaseUpgradeLedgerTest extends TestCase
{
    #[Test]
    public function ledger_reports_adopted_after_installation_row(): void
    {
        $ledger = new UpgradeLedger;

        $this->assertTrue($ledger->isAdopted(), '迁移后应已有采纳记录');
        $this->assertNotNull($ledger->currentVersion(), '采纳后数据库版本不应为空');
    }

    #[Test]
    public function adopting_writes_version_and_run(): void
    {
        DB::beginTransaction();

        try {
            $ledger = new UpgradeLedger;
            $runId = $ledger->begin('', '9.9.9', 0);
            $ledger->adopt('9.9.9', $runId);

            $this->assertSame('9.9.9', $ledger->currentVersion());
            $row = DB::table('system_upgrade_runs')->where('id', $runId)->first();
            $this->assertNotNull($row);
            $this->assertSame(UpgradeLedger::STATUS_ADOPTED, $row->status);
        } finally {
            DB::rollBack();
        }
    }

    #[Test]
    public function completing_advances_version_in_same_step(): void
    {
        DB::beginTransaction();

        try {
            $ledger = new UpgradeLedger;
            $runId = $ledger->begin('1.0.0', '2.0.0', 1);

            $ledger->complete($runId, '2.0.0', ['2026_10_21_999999_demo']);

            $this->assertSame('2.0.0', $ledger->currentVersion());

            $row = DB::table('system_upgrade_runs')->where('id', $runId)->first();
            $this->assertSame(UpgradeLedger::STATUS_COMPLETED, $row->status);
            $this->assertSame(1, (int) $row->executed_count);
            $this->assertStringContainsString('2026_10_21_999999_demo', (string) $row->executed_migrations);
        } finally {
            DB::rollBack();
        }
    }

    #[Test]
    public function failing_does_not_advance_version(): void
    {
        DB::beginTransaction();

        try {
            $before = (new UpgradeLedger)->currentVersion();
            $ledger = new UpgradeLedger;
            $runId = $ledger->begin($before ?? '', '3.0.0', 2);

            $ledger->fail($runId, UpgradeLedger::STATUS_FAILED, '模拟失败');

            $this->assertSame($before, $ledger->currentVersion(), '失败不得推进数据库版本');
            $row = DB::table('system_upgrade_runs')->where('id', $runId)->first();
            $this->assertSame(UpgradeLedger::STATUS_FAILED, $row->status);
            $this->assertSame('模拟失败', $row->error_message);
        } finally {
            DB::rollBack();
        }
    }

    #[Test]
    public function error_message_is_truncated_to_1000_chars(): void
    {
        DB::beginTransaction();

        try {
            $ledger = new UpgradeLedger;
            $runId = $ledger->begin('1.0.0', '2.0.0', 1);

            $ledger->fail($runId, UpgradeLedger::STATUS_FAILED, str_repeat('错', 2000));

            $row = DB::table('system_upgrade_runs')->where('id', $runId)->first();
            $this->assertSame(1000, mb_strlen((string) $row->error_message));
        } finally {
            DB::rollBack();
        }
    }

    #[Test]
    public function lock_is_exclusive(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'upgrade-lock-'.bin2hex(random_bytes(6)).'.lock';
        $inner = new UpgradeLock($path);

        try {
            $result = $inner->synchronized(function () use ($inner): string {
                // 同一进程内再次加锁必须失败：flock 对同进程的另一个句柄仍互斥。
                $blocked = false;
                try {
                    $inner->synchronized(static fn (): string => 'unexpected');
                } catch (RuntimeException $exception) {
                    $blocked = str_contains($exception->getMessage(), '正在执行');
                }

                return $blocked ? 'blocked' : 'not-blocked';
            });

            $this->assertSame('blocked', $result);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function adopt_on_already_adopted_database_is_rejected(): void
    {
        $manifest = VersionManifest::load();
        $service = app(DatabaseUpgradeService::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('不能再使用 --adopt');

        $service->upgrade($manifest, dryRun: false, skipBackup: true, adoptVersion: '9.9.9');
    }

    #[Test]
    public function dry_run_never_writes_run_record(): void
    {
        // dry-run 必须在写升级记录之前返回；即使被清单守卫拒绝，也不允许留下 running 记录。
        DB::beginTransaction();

        try {
            $before = DB::table('system_upgrade_runs')->count();
            $manifest = VersionManifest::load();
            $service = app(DatabaseUpgradeService::class);

            try {
                $service->upgrade($manifest, dryRun: true);
            } catch (RuntimeException) {
                // 测试库若存在非白名单孤儿，dry-run 会被守卫拒绝——拒绝本身也不得写记录。
            }

            $this->assertSame($before, DB::table('system_upgrade_runs')->count(), 'dry-run 不得写入升级记录');
        } finally {
            DB::rollBack();
        }
    }

    #[Test]
    public function baseline_inspector_detects_drift(): void
    {
        $inspector = new BaselineInspector;
        $file = sys_get_temp_dir().DIRECTORY_SEPARATOR.'baseline-'.bin2hex(random_bytes(6)).'.sql';

        try {
            file_put_contents($file, "CREATE TABLE a (id int);\nCREATE TABLE b (id int);\n");

            $matched = $inspector->compare($file, 2, 0);
            $this->assertFalse($matched['drifted']);
            $this->assertSame(2, $matched['baseline_tables']);

            $drifted = $inspector->compare($file, 3, 0);
            $this->assertTrue($drifted['drifted']);
        } finally {
            @unlink($file);
        }
    }

    #[Test]
    public function baseline_inspector_reports_missing_file(): void
    {
        $result = (new BaselineInspector)->compare('/nonexistent/baseline.sql', 5, 5);

        $this->assertFalse($result['available']);
        $this->assertTrue($result['drifted']);
    }
}
