<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\System\LedgerConsistencyCheck;
use Mockery;
use Tests\TestCase;

/**
 * reconcile:account-balance 的退出码口径：
 * 默认有差异即 FAILURE（人工巡检强口径）；调度传 --tolerate-legacy 时
 * 存量漂移只告警不失败，避免心跳因历史遗留差异长期红灯。
 */
class ReconcileAccountBalanceCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_returns_failure_when_snapshot_diff_exists(): void
    {
        $this->bindDiffs([$this->diffRow(314, 'referral_frozen', 2.75, 0.00, 2.75)]);

        $this->artisan('reconcile:account-balance', ['--json' => true])
            ->expectsOutputToContain('"diff_count": 1')
            ->assertExitCode(1);
    }

    public function test_tolerate_legacy_keeps_exit_code_zero_but_still_reports_diff(): void
    {
        $this->bindDiffs([$this->diffRow(314, 'referral_frozen', 2.75, 0.00, 2.75)]);

        $this->artisan('reconcile:account-balance', ['--tolerate-legacy' => true])
            ->expectsOutputToContain('对账完成：差异用户账户数 1')
            ->expectsOutputToContain('存量漂移容忍已开启')
            ->assertExitCode(0);

        // JSON 分支同样带容忍标记，便于调度日志回溯当时的口径。
        $this->artisan('reconcile:account-balance', ['--json' => true, '--tolerate-legacy' => true])
            ->expectsOutputToContain('"tolerate_legacy": true')
            ->assertExitCode(0);
    }

    public function test_returns_success_when_no_diff(): void
    {
        $this->bindDiffs([]);

        $this->artisan('reconcile:account-balance')->assertExitCode(0);
    }

    /**
     * @param  array<int, object>  $rows
     */
    private function bindDiffs(array $rows): void
    {
        $check = Mockery::mock(LedgerConsistencyCheck::class);
        $check->shouldReceive('snapshotBalanceDiffs')->andReturn($rows);

        $this->app->instance(LedgerConsistencyCheck::class, $check);
    }

    private function diffRow(int $userId, string $accountType, float $latest, float $balance, float $diff): object
    {
        return (object) [
            'user_id' => $userId,
            'account_type' => $accountType,
            'latest_balance_after' => $latest,
            'account_balance' => $balance,
            'diff' => $diff,
        ];
    }
}
