<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ExportDatabaseBackupJob;
use App\Models\AdminUser;
use App\Models\Role;
use App\Services\System\DatabaseBackupTaskService;
use App\Services\System\DatabaseStatusService;
use App\Support\AdminPermissions;
use App\Support\CacheKey;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Ramsey\Uuid\Uuid;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * 管理端数据库备份队列化契约回归：
 * 1. POST /database/backups 只投递任务并立即返回 task_id，mysqldump 不在请求里跑；
 * 2. 任务状态可轮询，未知/过期任务 404；
 * 3. 下载只认任务 ID 换回的服务端文件名，缓存里被塞进路径片段也必须拒绝；
 * 4. Job 失败时任务收敛为 failed 且异常向上冒泡（进入 failed_jobs）。
 */
class AdminDatabaseBackupTaskTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::DATABASE_MANAGE]));
    }

    public function test_export_backup_dispatches_job_and_returns_task_id_immediately(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v2/admin/database/backups');

        $response->assertOk();
        $taskId = (string) $response->json('data.task_id');
        $this->assertNotSame('', $taskId);
        $this->assertSame('queued', $response->json('data.status'));

        Queue::assertPushedOn('backup', ExportDatabaseBackupJob::class);

        $task = Cache::get(CacheKey::databaseBackupTask($taskId));
        $this->assertIsArray($task);
        $this->assertSame('queued', $task['status'] ?? null);
    }

    public function test_export_backup_returns_readable_error_when_backup_queue_name_is_invalid(): void
    {
        Queue::fake();
        // 备份队列名配成逗号串时 BackupQueue::name() 抛异常，接口必须给出可读提示，
        // 而不是落到兜底 renderer 变成「服务器内部错误」。
        config(['queue.caiwu_backup_queue' => 'backup,archive']);

        $this->postJson('/api/v2/admin/database/backups')
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('message', '备份队列名必须是单个队列名，不能是逗号分隔的多个队列：backup,archive');
    }

    public function test_dispatch_marks_task_failed_when_job_dispatch_throws(): void
    {
        // 投递阶段抛错（例如队列连接不可用）时，任务记录必须收敛为 failed：
        // 留在 queued 会挂满 TASK_TTL_SECONDS，用户既看不到失败原因也无法重提。
        $taskId = '11111111-1111-4111-8111-111111111111';
        Str::createUuidsUsing(static fn () => Uuid::fromString($taskId));

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andThrow(new RuntimeException('队列连接不可用'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        try {
            app(DatabaseBackupTaskService::class)->dispatch(1, '127.0.0.1');
            $this->fail('投递失败必须向上抛出，让调用方知道备份没有排上队');
        } catch (RuntimeException $exception) {
            $this->assertSame('队列连接不可用', $exception->getMessage());
        } finally {
            Str::createUuidsNormally();
        }

        $task = app(DatabaseBackupTaskService::class)->find($taskId);
        $this->assertNotNull($task, '投递失败也必须留下可查询的任务记录');
        $this->assertSame('failed', $task['status'], '投递失败的任务必须收敛为 failed');
        $this->assertSame('备份任务投递失败：队列连接不可用', $task['message']);
        $this->assertNull(Cache::get(CacheKey::databaseBackupInFlight()), '失败后必须释放在途标记，否则用户无法重提');
    }

    public function test_export_backup_rejects_second_submission_while_one_is_in_flight(): void
    {
        Queue::fake();

        $first = $this->postJson('/api/v2/admin/database/backups');
        $first->assertOk();
        $firstTaskId = (string) $first->json('data.task_id');

        $this->postJson('/api/v2/admin/database/backups')
            ->assertStatus(409)
            ->assertJsonPath('code', 40900);

        $this->assertSame($firstTaskId, Cache::get(CacheKey::databaseBackupInFlight()));
        $this->assertNotNull($this->getJson('/api/v2/admin/database/backups/'.$firstTaskId)->json('data'));
    }

    public function test_completed_task_releases_in_flight_marker(): void
    {
        Queue::fake();

        $taskId = (string) $this->postJson('/api/v2/admin/database/backups')->json('data.task_id');
        $this->assertSame($taskId, Cache::get(CacheKey::databaseBackupInFlight()));

        app(DatabaseBackupTaskService::class)->markCompleted($taskId, 'backup_20261022010101_abcdef.sql', 2048);

        $this->assertNull(Cache::get(CacheKey::databaseBackupInFlight()));
        $this->postJson('/api/v2/admin/database/backups')->assertOk();
    }

    public function test_show_backup_task_returns_current_status(): void
    {
        Queue::fake();

        $taskId = (string) $this->postJson('/api/v2/admin/database/backups')->json('data.task_id');

        $this->getJson('/api/v2/admin/database/backups/'.$taskId)
            ->assertOk()
            ->assertJsonPath('data.status', 'queued');
    }

    public function test_show_backup_task_returns_404_for_unknown_task(): void
    {
        $this->getJson('/api/v2/admin/database/backups/'.Str::uuid()->toString())
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);
    }

    public function test_download_requires_a_completed_task(): void
    {
        Queue::fake();

        $taskId = (string) $this->postJson('/api/v2/admin/database/backups')->json('data.task_id');

        $this->getJson('/api/v2/admin/database/backups/'.$taskId.'/download')
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);
    }

    public function test_download_rejects_filename_injected_into_task_cache(): void
    {
        $taskId = Str::uuid()->toString();
        Cache::put(CacheKey::databaseBackupTask($taskId), [
            'status' => 'completed',
            'message' => '备份已完成，可以下载',
            'filename' => '../../.env',
            'size_bytes' => 1024,
            'finished_at' => now()->toDateTimeString(),
        ], 600);

        $this->getJson('/api/v2/admin/database/backups/'.$taskId.'/download')
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);
    }

    public function test_job_marks_task_completed_on_success(): void
    {
        $taskId = Str::uuid()->toString();

        $backup = [
            'absolute_path' => 'E:/tmp/backup_20261022010101_abcdef.sql',
            'filename' => 'backup_20261022010101_abcdef.sql',
            'size_bytes' => 2048,
        ];

        $service = Mockery::mock(DatabaseStatusService::class);
        $service->shouldReceive('createBackup')->once()->andReturn($backup);

        $job = new ExportDatabaseBackupJob($taskId, 1, '127.0.0.1');
        $job->handle(app(DatabaseBackupTaskService::class), $service);

        $task = app(DatabaseBackupTaskService::class)->find($taskId);
        $this->assertSame('completed', $task['status'] ?? null);
        $this->assertSame($backup['filename'], $task['filename'] ?? null);
        $this->assertSame(2048, $task['size_bytes'] ?? null);
    }

    public function test_job_marks_task_failed_and_rethrows(): void
    {
        $taskId = Str::uuid()->toString();

        $service = Mockery::mock(DatabaseStatusService::class);
        $service->shouldReceive('createBackup')->once()->andThrow(new RuntimeException('未找到 mysqldump'));

        $job = new ExportDatabaseBackupJob($taskId, 1, '127.0.0.1');

        try {
            $job->handle(app(DatabaseBackupTaskService::class), $service);
            $this->fail('备份失败必须向上抛出，交由队列记录 failed_jobs');
        } catch (RuntimeException $exception) {
            $this->assertSame('未找到 mysqldump', $exception->getMessage());
        }

        $task = app(DatabaseBackupTaskService::class)->find($taskId);
        $this->assertSame('failed', $task['status'] ?? null);
        $this->assertSame('未找到 mysqldump', $task['message'] ?? null);
    }

    public function test_prune_removes_stale_partial_files_only(): void
    {
        $directory = storage_path('framework/testing/database-backups-'.Str::lower(Str::random(8)));
        File::ensureDirectoryExists($directory, 0750);

        try {
            $stalePartial = $directory.'/backup_20260101000000_aaaaaa.sql.part';
            $freshPartial = $directory.'/backup_20260101000001_bbbbbb.sql.part';
            $finished = $directory.'/backup_20260101000002_cccccc.sql';

            File::put($stalePartial, 'partial');
            File::put($freshPartial, 'partial');
            File::put($finished, 'dump');

            touch($stalePartial, now()->getTimestamp() - 7200);

            $method = new ReflectionMethod(DatabaseStatusService::class, 'pruneOldBackups');
            $method->setAccessible(true);
            $method->invoke(app(DatabaseStatusService::class), $directory);

            $this->assertFileDoesNotExist($stalePartial, '超龄 .part 必须清理，否则会长期占用目录');
            $this->assertFileExists($freshPartial, '一小时内的 .part 可能是正在导出的产物，不能删');
            $this->assertFileExists($finished, '保留策略只按数量裁剪 .sql，单个文件不该被删');
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_backup_job_queue_configuration(): void
    {
        $job = new ExportDatabaseBackupJob(Str::uuid()->toString());

        $this->assertSame(1, $job->tries, '备份不可重试，避免重复产物');
        $this->assertSame(900, $job->timeout, '必须大于 mysqldump 的 600 秒超时');
        $this->assertSame('backup', $job->queue, '备份独占 backup 队列，避免 900s 的长任务阻塞业务队列');
    }

    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '数据库备份测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '数据库备份测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
