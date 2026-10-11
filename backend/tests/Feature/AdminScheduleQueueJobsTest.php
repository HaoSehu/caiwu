<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\Role;
use App\Services\Admin\Rbac\PermissionCatalogService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理端队列任务治理接口契约回归（jobs / failed_jobs）：
 * 1. 列表（schedule.view）：pending/failed 分页返回 payload 解析后的任务名、时间与异常预览；
 * 2. 删除与清空（schedule.queue_manage）：破坏性操作必须显式确认文本、审计落 activity_logs；
 * 3. 权限边界：仅 schedule.view 只能看不能删，queue_manage 隐含 schedule.view；
 * 4. 权限目录登记：system_schedule 组 / high 风险级，默认不下发任何内置角色。
 * 测试库为共享开发库，jobs/failed_jobs 计数一律用「基线增量」断言；DatabaseTransactions 回滚兜底。
 */
class AdminScheduleQueueJobsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::SCHEDULE_QUEUE_MANAGE]));
    }

    public function test_queue_manage_permission_is_catalogued_but_not_defaulted(): void
    {
        $item = null;
        foreach (app(PermissionCatalogService::class)->list() as $entry) {
            if (($entry['key'] ?? '') === AdminPermissions::SCHEDULE_QUEUE_MANAGE) {
                $item = $entry;
                break;
            }
        }

        $this->assertNotNull($item, 'schedule.queue_manage 必须出现在权限目录');
        $this->assertSame('system_schedule', $item['group']);
        $this->assertSame('high', $item['risk_level']);
        $this->assertSame('管理队列任务', $item['name']);
        $this->assertTrue((bool) $item['is_dangerous']);

        $this->assertNotContains(AdminPermissions::SCHEDULE_QUEUE_MANAGE, AdminPermissions::visitorPermissions());
        $this->assertNotContains(AdminPermissions::SCHEDULE_QUEUE_MANAGE, AdminPermissions::adminDefaultPermissions());

        // queue_manage 隐含 schedule.view：能管理队列必然能查看
        $this->assertContains(AdminPermissions::SCHEDULE_VIEW, AdminPermissions::impliedPermissions(AdminPermissions::SCHEDULE_QUEUE_MANAGE));
    }

    public function test_list_returns_pending_jobs_with_parsed_payload(): void
    {
        $jobId = $this->insertPendingJob('App\\Jobs\\RunHeartbeatTaskJob', 'automation');

        $response = $this->getJson('/api/v2/admin/schedule-queue-jobs?queue=pending&page=1&page_size=20');

        $response->assertOk();
        $rows = $response->json('data.list') ?? [];
        $matched = null;
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $jobId) {
                $matched = $row;
                break;
            }
        }

        $this->assertNotNull($matched, '新插入的 pending 任务必须出现在分页结果中');
        $this->assertSame('App\\Jobs\\RunHeartbeatTaskJob', $matched['display_name']);
        $this->assertSame('pending', $matched['kind']);
        $this->assertSame('automation', $matched['queue']);
        $this->assertSame('pending', $matched['state']);
        $this->assertSame(0, (int) $matched['attempts']);
    }

    public function test_list_returns_failed_jobs_with_exception_preview(): void
    {
        $jobId = $this->insertFailedJob('App\\Jobs\\BrokenJob', "SQLSTATE[HY000]: connection refused\n  at /var/www/vendor/laravel/framework/src/...长堆栈");

        $response = $this->getJson('/api/v2/admin/schedule-queue-jobs?queue=failed&page=1&page_size=20');

        $response->assertOk();
        $rows = $response->json('data.list') ?? [];
        $matched = null;
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $jobId) {
                $matched = $row;
                break;
            }
        }

        $this->assertNotNull($matched, '新插入的 failed 任务必须出现在分页结果中');
        $this->assertSame('App\\Jobs\\BrokenJob', $matched['display_name']);
        $this->assertSame('failed', $matched['kind']);
        $this->assertSame('failed', $matched['state']);
        $this->assertStringContainsString('connection refused', (string) $matched['exception']);
        $this->assertDoesNotMatchRegularExpression('/\n/', (string) $matched['exception'], '异常预览必须压平为单行');
        $this->assertNotNull($matched['failed_at']);
    }

    public function test_list_rejects_unsupported_queue(): void
    {
        $this->getJson('/api/v2/admin/schedule-queue-jobs?queue=redis')
            ->assertStatus(422);
    }

    public function test_delete_removes_single_job_and_writes_audit(): void
    {
        $jobId = $this->insertPendingJob('App\\Jobs\\DeletableJob', 'default');
        $baseline = $this->pendingCount();

        $this->deleteJson("/api/v2/admin/schedule-queue-jobs/pending/{$jobId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame($baseline - 1, $this->pendingCount(), '单条删除后 pending 计数应 -1');
        $this->assertDatabaseMissing('jobs', ['id' => $jobId]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'schedule',
            'action' => 'schedule.queue.job_delete',
            'subject_id' => $jobId,
        ]);
    }

    public function test_delete_missing_job_returns_validation_error(): void
    {
        $this->deleteJson('/api/v2/admin/schedule-queue-jobs/pending/999999999')
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);
    }

    public function test_clear_requires_exact_confirm_text(): void
    {
        $this->deleteJson('/api/v2/admin/schedule-queue-jobs/pending', ['confirm_text' => '清空'])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);

        $this->deleteJson('/api/v2/admin/schedule-queue-jobs/pending')
            ->assertStatus(422);
    }

    public function test_clear_deletes_all_pending_jobs_and_reports_count(): void
    {
        $this->insertPendingJob('App\\Jobs\\ClearTargetA', 'default');
        $this->insertPendingJob('App\\Jobs\\ClearTargetB', 'automation');
        $baseline = $this->pendingCount();
        $this->assertGreaterThan(0, $baseline);

        $this->deleteJson('/api/v2/admin/schedule-queue-jobs/pending', ['confirm_text' => '立即清空'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame(0, $this->pendingCount(), '清空后 pending 表应为 0');
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'schedule',
            'action' => 'schedule.queue.clear',
        ]);
    }

    public function test_view_only_admin_can_list_but_not_mutate(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::SCHEDULE_VIEW]));

        $this->getJson('/api/v2/admin/schedule-queue-jobs?queue=pending')->assertOk();

        $jobId = $this->insertPendingJob('App\\Jobs\\GuardedJob', 'default');

        $this->deleteJson("/api/v2/admin/schedule-queue-jobs/pending/{$jobId}")
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);

        $this->deleteJson('/api/v2/admin/schedule-queue-jobs/pending', ['confirm_text' => '立即清空'])
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);
    }

    private function pendingCount(): int
    {
        return (int) DB::table('jobs')->count();
    }

    private function insertPendingJob(string $displayName, string $queue): int
    {
        return (int) DB::table('jobs')->insertGetId([
            'queue' => $queue,
            'payload' => json_encode([
                'uuid' => 'test-'.uniqid(),
                'displayName' => $displayName,
                'job' => 'Illuminate\\Queue\\CallQueuedClosure',
                'maxTries' => 3,
            ], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->getTimestamp(),
            'created_at' => now()->getTimestamp(),
        ]);
    }

    private function insertFailedJob(string $displayName, string $exception): int
    {
        return (int) DB::table('failed_jobs')->insertGetId([
            'uuid' => 'test-'.uniqid(),
            'connection' => 'database',
            'queue' => 'automation',
            'payload' => json_encode([
                'uuid' => 'test-'.uniqid(),
                'displayName' => $displayName,
                'job' => 'Illuminate\\Queue\\CallQueuedClosure',
                'maxTries' => 3,
            ], JSON_THROW_ON_ERROR),
            'exception' => $exception,
            'failed_at' => now(),
        ]);
    }

    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '队列任务测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '队列任务测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
