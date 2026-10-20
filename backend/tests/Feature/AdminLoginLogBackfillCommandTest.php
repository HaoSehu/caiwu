<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminLoginLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 历史登录审计回填命令（log:backfill-admin-login-logs）：
 * 1. 默认 dry-run 不写入；
 * 2. --execute 按 activity_logs admin.login 复制到 admin_login_logs；
 * 3. legacy_activity_log_id 唯一键保证重复执行幂等；
 * 4. 历史记录无浏览器/OS 归纳数据，保持空串。
 */
class AdminLoginLogBackfillCommandTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dry_run_does_not_write(): void
    {
        $legacyId = $this->insertLegacyLogin('dry_'.uniqid());

        $this->artisan('log:backfill-admin-login-logs')
            ->expectsOutputToContain('dry-run 完成')
            ->assertExitCode(0);

        $this->assertFalse(
            AdminLoginLog::query()->where('legacy_activity_log_id', $legacyId)->exists(),
            'dry-run 不得写入任何记录',
        );
    }

    public function test_execute_backfills_legacy_login_and_is_idempotent(): void
    {
        $username = 'legacy_'.uniqid();
        $legacyId = $this->insertLegacyLogin($username);

        $this->artisan('log:backfill-admin-login-logs', ['--execute' => true])
            ->expectsOutputToContain('回填完成')
            ->assertExitCode(0);

        $row = AdminLoginLog::query()->where('legacy_activity_log_id', $legacyId)->first();
        $this->assertInstanceOf(AdminLoginLog::class, $row);
        $this->assertSame($username, $row->username);
        $this->assertSame('历史管理员', $row->nickname);
        $this->assertSame('超级管理员', $row->role_name);
        $this->assertSame(AdminLoginLog::STATUS_SUCCESS, $row->status);
        $this->assertSame('', $row->failure_reason);
        $this->assertSame('198.51.100.7', $row->client_ip);
        $this->assertSame('', $row->browser);
        $this->assertSame('', $row->operating_system);
        $this->assertSame('2026-09-01 10:00:00', $row->logged_at?->format('Y-m-d H:i:s'));

        // 重复执行不产生重复行。
        $this->artisan('log:backfill-admin-login-logs', ['--execute' => true])->assertExitCode(0);
        $this->assertSame(
            1,
            AdminLoginLog::query()->where('legacy_activity_log_id', $legacyId)->count(),
            'legacy_activity_log_id 唯一键必须保证幂等',
        );
    }

    private function insertLegacyLogin(string $username): int
    {
        return (int) DB::table('activity_logs')->insertGetId([
            'event_id' => 'test:'.uniqid(),
            'stream' => 'auth',
            'actor_type' => 'admin',
            'actor_id' => 123456,
            'actor_name' => $username,
            'module' => 'auth',
            'action' => 'admin.login',
            'description' => '管理员登录',
            'context' => json_encode([
                'admin_username' => $username,
                'admin_nickname' => '历史管理员',
                'role_name' => '超级管理员',
            ], JSON_UNESCAPED_UNICODE),
            'ip_address' => '198.51.100.7',
            'created_at' => '2026-09-01 10:00:00',
            'updated_at' => '2026-09-01 10:00:00',
        ]);
    }
}
