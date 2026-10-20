<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminLoginLog;
use App\Models\AdminUser;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理员登录日志独立表（对齐 howstack-bos 登录审计模型）：
 * 1. 成功与失败都落 admin_login_logs，失败保留原因；
 * 2. activity_logs 不再重复记录 admin.login，也不再有登录接口访问审计；
 * 3. 限流提前拒绝（429）不写入登录日志；
 * 4. admin-logins channel 读取新表，支持成功/失败状态筛选与详情；
 * 5. 清理支持 dry_run 预览：只统计不删除，显式执行才物理删除。
 * 测试库为共享开发库，计数一律用「基线增量」断言；DatabaseTransactions 回滚兜底。
 */
class AdminLoginLogTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // 登录失败计数与锁行走 redis_volatile，测试环境替换为 array store。
        config(['cache.stores.redis_volatile' => ['driver' => 'array']]);
    }

    public function test_successful_login_writes_login_log_without_activity_log_duplicate(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
        )->postJson('/api/v2/admin/login', [
            'username' => $admin->username,
            'password' => 'secret123',
        ]);

        $response->assertOk();

        $log = AdminLoginLog::query()->where('username', $admin->username)->first();
        $this->assertInstanceOf(AdminLoginLog::class, $log);
        $this->assertSame(AdminLoginLog::STATUS_SUCCESS, $log->status);
        $this->assertSame('', $log->failure_reason);
        $this->assertSame(AdminUser::query()->findOrFail($admin->id)->nickname, $log->nickname);
        $this->assertSame('127.0.0.1', $log->client_ip);
        $this->assertSame('Chrome 130.0.0.0', $log->browser);
        $this->assertSame('Windows 10/11', $log->operating_system);

        // 登录成功不再写 activity_logs 的 admin.login 与访问审计行。
        $this->assertSame(0, DB::table('activity_logs')
            ->where('actor_id', $admin->id)
            ->where('action', 'admin.login')
            ->count());
        $this->assertSame(0, DB::table('activity_logs')
            ->where('actor_id', $admin->id)
            ->where('action', 'POST api/v2/admin/login')
            ->count());
    }

    public function test_wrong_password_records_invalid_credentials_failure(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->postJson('/api/v2/admin/login', [
            'username' => $admin->username,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);

        $log = AdminLoginLog::query()->where('username', $admin->username)->first();
        $this->assertInstanceOf(AdminLoginLog::class, $log);
        $this->assertSame(AdminLoginLog::STATUS_FAILED, $log->status);
        $this->assertSame(AdminLoginLog::REASON_INVALID_CREDENTIALS, $log->failure_reason);
        $this->assertSame((int) $admin->id, (int) $log->admin_user_id);
    }

    public function test_unknown_username_records_input_as_nickname(): void
    {
        // 系统需至少存在一个管理员才进入账号密码校验分支。
        $this->makeAdmin();
        $username = 'unknown_'.uniqid();

        $this->postJson('/api/v2/admin/login', [
            'username' => $username,
            'password' => 'whatever',
        ])->assertStatus(401);

        $log = AdminLoginLog::query()->where('username', $username)->first();
        $this->assertInstanceOf(AdminLoginLog::class, $log);
        $this->assertNull($log->admin_user_id);
        $this->assertSame($username, $log->nickname);
        $this->assertSame(AdminLoginLog::REASON_INVALID_CREDENTIALS, $log->failure_reason);
    }

    public function test_disabled_account_records_account_disabled_failure(): void
    {
        $admin = $this->makeAdmin(status: 0);

        $this->postJson('/api/v2/admin/login', [
            'username' => $admin->username,
            'password' => 'secret123',
        ])->assertStatus(403);

        $log = AdminLoginLog::query()->where('username', $admin->username)->first();
        $this->assertInstanceOf(AdminLoginLog::class, $log);
        $this->assertSame(AdminLoginLog::STATUS_FAILED, $log->status);
        $this->assertSame(AdminLoginLog::REASON_ACCOUNT_DISABLED, $log->failure_reason);
    }

    public function test_rate_limited_attempts_are_not_recorded(): void
    {
        $admin = $this->makeAdmin();

        // throttle:5,1 允许每分钟 5 次；第 6 次由中间件直接 429。
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v2/admin/login', [
                'username' => $admin->username,
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v2/admin/login', [
            'username' => $admin->username,
            'password' => 'wrong-password',
        ])->assertStatus(429);

        $this->assertSame(5, AdminLoginLog::query()->where('username', $admin->username)->count());
    }

    public function test_admin_logins_channel_reads_new_table_with_status_filter(): void
    {
        Sanctum::actingAs($this->makeAdmin(permissions: [AdminPermissions::LOG_LIST]));

        $token = 'ui_'.uniqid();
        AdminLoginLog::query()->create([
            'admin_user_id' => null,
            'username' => $token,
            'nickname' => $token,
            'role_name' => '',
            'status' => AdminLoginLog::STATUS_FAILED,
            'failure_reason' => AdminLoginLog::REASON_INVALID_CREDENTIALS,
            'client_ip' => '203.0.113.10',
            'browser' => 'Firefox 128.0',
            'operating_system' => 'Linux',
            'logged_at' => now(),
        ]);

        $response = $this->getJson('/api/v2/admin/logs/admin-logins?keyword='.$token.'&status=failed&include_summary=1');

        $response->assertOk();
        $rows = $response->json('data.list') ?? [];
        $this->assertCount(1, $rows);
        $this->assertSame($token, $rows[0]['admin_username']);
        $this->assertSame('failed', $rows[0]['status']);
        $this->assertSame('Firefox 128.0', $rows[0]['browser']);
        $this->assertSame('Linux', $rows[0]['operating_system']);
        $this->assertSame(AdminLoginLog::REASON_INVALID_CREDENTIALS, $rows[0]['failure_reason']);

        $detail = $this->getJson('/api/v2/admin/logs/admin-logins/'.$rows[0]['id']);
        $detail->assertOk();
        $this->assertSame('admin_login_logs', $detail->json('data.log.source'));
        $this->assertSame('failed', $detail->json('data.log.fields.status'));
    }

    public function test_cleanup_dry_run_previews_without_deleting_then_execute_deletes(): void
    {
        Sanctum::actingAs($this->makeAdmin(permissions: [AdminPermissions::LOG_MANAGE]));

        $oldToken = 'old_'.uniqid();
        $cutoff = now()->subDays(180)->startOfDay();
        $baseline = AdminLoginLog::query()->where('logged_at', '<', $cutoff)->count();

        $old = AdminLoginLog::query()->create([
            'admin_user_id' => null,
            'username' => $oldToken,
            'nickname' => $oldToken,
            'role_name' => '',
            'status' => AdminLoginLog::STATUS_FAILED,
            'failure_reason' => AdminLoginLog::REASON_INVALID_CREDENTIALS,
            'client_ip' => '203.0.113.11',
            'browser' => '',
            'operating_system' => '',
            'logged_at' => now()->subDays(400),
        ]);

        $preview = $this->postJson('/api/v2/admin/log-cleanups', [
            'type' => 'admin_login',
            'keep_days' => 180,
            'dry_run' => true,
        ]);

        $preview->assertOk();
        $this->assertTrue((bool) $preview->json('data.detail.cleanup.dry_run'));
        $this->assertSame($baseline + 1, (int) $preview->json('data.detail.cleanup.affected.admin_login'));
        $this->assertTrue(AdminLoginLog::query()->whereKey($old->id)->exists(), '预览不得删除数据');

        $execute = $this->postJson('/api/v2/admin/log-cleanups', [
            'type' => 'admin_login',
            'keep_days' => 180,
            'confirm_text' => '立即清理',
        ]);

        $execute->assertOk();
        $this->assertFalse((bool) $execute->json('data.detail.cleanup.dry_run'));
        $this->assertFalse(AdminLoginLog::query()->whereKey($old->id)->exists(), '显式执行后才允许删除');
    }

    public function test_overlong_username_is_truncated_to_column_width(): void
    {
        // LoginRequest 不限制 username 长度；超长输入不得因列溢出丢失整条审计。
        $this->makeAdmin();
        $username = str_repeat('u', 120);

        $this->postJson('/api/v2/admin/login', [
            'username' => $username,
            'password' => 'whatever',
        ])->assertStatus(401);

        $log = AdminLoginLog::query()->where('username', mb_substr($username, 0, 64))->first();
        $this->assertInstanceOf(AdminLoginLog::class, $log);
        $this->assertSame(64, mb_strlen($log->username));
        $this->assertSame(64, mb_strlen($log->nickname));
    }

    public function test_cleanup_execution_still_requires_confirm_text(): void
    {
        Sanctum::actingAs($this->makeAdmin(permissions: [AdminPermissions::LOG_MANAGE]));

        $this->postJson('/api/v2/admin/log-cleanups', [
            'type' => 'admin_login',
            'keep_days' => 180,
        ])->assertStatus(422);
    }

    private function makeAdmin(
        array $permissions = [AdminPermissions::LOG_LIST],
        int $status = 1,
    ): AdminUser {
        $token = uniqid();
        $role = Role::query()->create([
            'name' => 'role_'.$token,
            'label' => '登录日志测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.$token,
            'password' => 'secret123',
            'nickname' => '登录日志测试管理员',
            'status' => $status,
            'role_id' => (int) $role->id,
        ]);
    }
}
