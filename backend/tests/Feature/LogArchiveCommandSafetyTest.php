<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * 归档命令（V2）安全网：只覆盖「参数组合拒绝路径 + 只读模式冒烟」。
 *
 * 所有用例不触发 archive / purge / cleanup 的真实执行（破坏性路径仅验证网关拒绝），
 * 保证在任何环境（含共享测试库）执行都不会误删数据。
 */
class LogArchiveCommandSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_v2_overview_is_read_only_and_succeeds(): void
    {
        $this->artisan('db:archive-logs-v2')
            ->expectsOutputToContain('模式: overview')
            ->assertExitCode(0);
    }

    public function test_v2_overview_json_reports_tables_and_eligible(): void
    {
        $exit = Artisan::call('db:archive-logs-v2', ['--json' => true]);
        $payload = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exit);
        $this->assertSame('overview', $payload['mode'] ?? null);
        $this->assertIsInt($payload['retention_days'] ?? null);
        $this->assertIsInt($payload['total_eligible'] ?? null);
        $this->assertArrayHasKey('activity_logs', $payload['tables'] ?? []);
        $this->assertIsBool($payload['tables']['activity_logs']['available'] ?? null);
    }

    public function test_v2_rejects_restore_combined_with_execute(): void
    {
        $this->artisan('db:archive-logs-v2', ['--restore-dry-run' => true, '--execute' => true])
            ->expectsOutputToContain('不能与其他执行模式组合')
            ->assertExitCode(1);
    }

    public function test_v2_rejects_cleanup_combined_with_purge(): void
    {
        $this->artisan('db:archive-logs-v2', ['--cleanup' => true, '--purge' => true, '--batch-id' => 'batch-x'])
            ->expectsOutputToContain('不能与归档或清除模式组合')
            ->assertExitCode(1);
    }

    public function test_v2_rejects_standalone_purge_without_batch_id(): void
    {
        $this->artisan('db:archive-logs-v2', ['--purge' => true])
            ->expectsOutputToContain('必须配合 --batch-id')
            ->assertExitCode(1);
    }

    public function test_v2_rejects_standalone_purge_with_table(): void
    {
        $this->artisan('db:archive-logs-v2', [
            '--purge' => true,
            '--batch-id' => 'batch-x',
            '--table' => ['activity_logs'],
        ])
            ->expectsOutputToContain('不接受 --table')
            ->assertExitCode(1);
    }

    public function test_v2_rejects_restore_with_table(): void
    {
        $this->artisan('db:archive-logs-v2', [
            '--restore-dry-run' => true,
            '--batch-id' => 'batch-x',
            '--table' => ['activity_logs'],
        ])
            ->expectsOutputToContain('不接受 --table')
            ->assertExitCode(1);
    }

    public function test_v2_rejects_cleanup_with_retention_override(): void
    {
        $this->artisan('db:archive-logs-v2', ['--cleanup' => true, '--retain-days' => '30'])
            ->expectsOutputToContain('不能与 --table、--batch-id 或 --retain-days 组合')
            ->assertExitCode(1);
    }

    public function test_v2_rejects_file_retain_days_without_cleanup(): void
    {
        $this->artisan('db:archive-logs-v2', ['--file-retain-days' => '30'])
            ->expectsOutputToContain('只能与 --cleanup 组合')
            ->assertExitCode(1);
    }

    public function test_v2_rejects_non_integer_retention(): void
    {
        $this->artisan('db:archive-logs-v2', ['--retain-days' => 'abc'])
            ->expectsOutputToContain('must be an integer')
            ->assertExitCode(1);
    }

    public function test_v2_rejects_excluded_audit_table(): void
    {
        $this->artisan('db:archive-logs-v2', ['--table' => ['payments']])
            ->expectsOutputToContain('cannot be archived by this command')
            ->assertExitCode(1);
    }

    public function test_v2_rejects_unknown_table(): void
    {
        $this->artisan('db:archive-logs-v2', ['--table' => ['definitely_not_a_log_table']])
            ->expectsOutputToContain('Unsupported log archive table')
            ->assertExitCode(1);
    }

    public function test_list_rejects_out_of_range_limit(): void
    {
        $this->artisan('db:archive-logs-v2:list', ['--limit' => '0'])
            ->expectsOutputToContain('必须在 1 到 500 之间')
            ->assertExitCode(1);

        $this->artisan('db:archive-logs-v2:list', ['--limit' => '501'])
            ->expectsOutputToContain('必须在 1 到 500 之间')
            ->assertExitCode(1);
    }

    public function test_list_rejects_invalid_status(): void
    {
        $this->artisan('db:archive-logs-v2:list', ['--status' => 'bogus'])
            ->expectsOutputToContain('不是有效归档状态')
            ->assertExitCode(1);
    }

    public function test_list_default_is_read_only_and_succeeds(): void
    {
        $this->artisan('db:archive-logs-v2:list')
            ->assertExitCode(0);
    }

    public function test_search_requires_table(): void
    {
        $this->artisan('db:archive-logs-v2:search')
            ->expectsOutputToContain('--table 必填')
            ->assertExitCode(1);
    }

    public function test_search_requires_date_range(): void
    {
        $this->artisan('db:archive-logs-v2:search', ['--table' => 'activity_logs'])
            ->expectsOutputToContain('必须同时指定 start_date 和 end_date')
            ->assertExitCode(1);
    }

    public function test_search_rejects_start_after_end(): void
    {
        $this->artisan('db:archive-logs-v2:search', [
            '--table' => 'activity_logs',
            '--start-date' => '2026-02-01',
            '--end-date' => '2026-01-01',
        ])
            ->expectsOutputToContain('start_date 不能晚于 end_date')
            ->assertExitCode(1);
    }

    public function test_search_rejects_excluded_table(): void
    {
        $this->artisan('db:archive-logs-v2:search', [
            '--table' => 'payments',
            '--start-date' => '2026-01-01',
            '--end-date' => '2026-02-01',
        ])
            ->expectsOutputToContain('cannot be archived by this command')
            ->assertExitCode(1);
    }
}
