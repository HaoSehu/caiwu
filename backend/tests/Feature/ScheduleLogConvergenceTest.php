<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ScheduleRunLog;
use App\Services\System\AdminLogService;
use App\Services\System\AdminLogV2QueryService;
use App\Services\System\ScheduleRunLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * V9-02 调度日志三写收敛（2026-10-10 方案 A）：
 * 1. record() 停写 schedule_run_logs，仅保留 activity_logs(module=cron) 审计镜像
 *    （心跳台账 schedule_task_runs 由 ScheduleTaskRunRepository 独立写入，不在本测试范围）；
 * 2. 任务日志视图以「最后一条存量行 + 60 秒」为冻结边界：边界前的镜像行（存量重复记录）
 *    不展示，边界后的镜像行（停写后的真实新执行）必须展示，存量行本身保留只读展示；
 * 3. activity-{id} 形态的镜像条目在任务日志详情中可解析。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ScheduleLogConvergenceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_record_success_writes_only_activity_mirror(): void
    {
        $taskName = '收敛验证任务-'.uniqid();
        $before = ScheduleRunLog::query()->count();

        $result = app(ScheduleRunLogService::class)->record(
            $taskName,
            static fn (): array => ['processed' => 3],
            ['task_key' => 'convergence-probe', 'source' => 'test'],
        );

        $this->assertSame(['processed' => 3], $result, 'record() 必须透传任务返回值');
        $this->assertSame($before, ScheduleRunLog::query()->count(), '停写后不得新增 schedule_run_logs 行');

        $mirror = ActivityLog::query()
            ->where('module', 'cron')
            ->where('description', 'Cron_'.$taskName.'执行完成')
            ->first();

        $this->assertInstanceOf(ActivityLog::class, $mirror, '成功路径必须落审计镜像');
        $this->assertSame('success', (string) $mirror->action);
        $this->assertSame('convergence-probe', (string) ($mirror->context['task_key'] ?? ''));
        $this->assertSame(3, (int) ($mirror->context['summary']['processed'] ?? 0));
    }

    public function test_record_failure_skips_legacy_row_and_mirrors_failure(): void
    {
        $taskName = '收敛失败任务-'.uniqid();
        $before = ScheduleRunLog::query()->count();

        try {
            app(ScheduleRunLogService::class)->record(
                $taskName,
                static function (): array {
                    throw new RuntimeException('收敛测试异常');
                },
                ['task_key' => 'convergence-fail-probe'],
            );
            $this->fail('任务异常必须向上抛出，不得被 record() 吞掉');
        } catch (RuntimeException $exception) {
            $this->assertSame('收敛测试异常', $exception->getMessage());
        }

        $this->assertSame($before, ScheduleRunLog::query()->count(), '失败路径不得新增 schedule_run_logs 行');

        $mirror = ActivityLog::query()
            ->where('module', 'cron')
            ->where('description', 'Cron_'.$taskName.'执行失败：收敛测试异常')
            ->first();

        $this->assertInstanceOf(ActivityLog::class, $mirror, '失败路径必须落失败审计镜像');
        $this->assertSame('failed', (string) $mirror->action);
        $this->assertStringContainsString('收敛测试异常', (string) ($mirror->context['error_message'] ?? ''));
    }

    public function test_task_view_applies_freeze_boundary_between_legacy_rows_and_new_mirrors(): void
    {
        // 存量行：模拟 schedule_run_logs 冻结前最后一次执行（2 小时前，为全表最新一行 → 定义冻结边界）。
        $legacyAt = now()->subHours(2);
        // 日期窗口按存量行日期取起：跨零点时段（00:00-02:00）存量行落在前一日，
        // 若用 now()->toDateString() 做起点会把存量行排除在窗口外，造成时间敏感失败。
        $filters = ['start_date' => $legacyAt->toDateString(), 'end_date' => now()->toDateString()];

        $legacyName = '收敛存量任务-'.uniqid();
        DB::table('schedule_run_logs')->insert([
            'task_name' => $legacyName,
            'status' => 'success',
            'duration_ms' => 11,
            'summary' => json_encode(['legacy' => true]),
            'error_msg' => null,
            'started_at' => $legacyAt->toDateTimeString(),
            'finished_at' => $legacyAt->toDateTimeString(),
            'created_at' => $legacyAt->toDateTimeString(),
            'updated_at' => $legacyAt->toDateTimeString(),
        ]);

        // 旧镜像：同次执行的重复记录（写入偏差 3 秒，位于冻结边界 +60 秒容忍窗口内）→ 应被滤除。
        $oldMirrorKey = 'convergence-old-mirror-'.uniqid();
        $this->insertCronMirror($oldMirrorKey, 'success', 11, $legacyAt->copy()->addSeconds(3));

        // 新镜像：停写后的真实新执行 → 必须展示。
        $freshMirrorKey = 'convergence-fresh-mirror-'.uniqid();
        $this->insertCronMirror($freshMirrorKey, 'success', 22, now());

        Cache::flush();
        $payload = app(AdminLogService::class)->getTaskLogs($filters, 1, 100);
        $entries = collect($payload['data'] ?? []);

        $this->assertTrue(
            $entries->contains(static fn (array $entry): bool => ($entry['task_key'] ?? '') === $legacyName),
            '存量行应保留只读展示'
        );
        $this->assertTrue(
            $entries->contains(static fn (array $entry): bool => ($entry['task_key'] ?? '') === $freshMirrorKey),
            '冻结边界后的新镜像必须展示'
        );
        $this->assertFalse(
            $entries->contains(static fn (array $entry): bool => ($entry['task_key'] ?? '') === $oldMirrorKey),
            '冻结边界前的镜像属重复记录，不得重复展示'
        );

        $summary = app(AdminLogService::class)->getTaskLogsSummary($filters);
        $this->assertGreaterThanOrEqual(2, (int) ($summary['total'] ?? 0), '汇总须同时覆盖存量行与新镜像');
    }

    public function test_v2_detail_resolves_activity_prefixed_task_log_id(): void
    {
        $taskName = '收敛详情任务-'.uniqid();
        app(ScheduleRunLogService::class)->record(
            $taskName,
            static fn (): array => ['ok' => true],
            ['task_key' => 'convergence-detail-probe'],
        );

        $mirror = ActivityLog::query()
            ->where('module', 'cron')
            ->where('description', 'Cron_'.$taskName.'执行完成')
            ->firstOrFail();

        $detail = app(AdminLogV2QueryService::class)->detail('tasks', 'activity-'.$mirror->id);

        $this->assertSame('activity-'.$mirror->id, $detail['id']);
        $this->assertSame('activity_logs', $detail['source']);
        $this->assertSame('convergence-detail-probe', $detail['fields']['task_key']);
        $this->assertSame('success', $detail['fields']['status']);
        $this->assertSame('Cron_'.$taskName.'执行完成', $detail['message']);
    }

    private function insertCronMirror(string $taskKey, string $status, int $durationMs, Carbon $createdAt): void
    {
        DB::table('activity_logs')->insert([
            'event_id' => (string) Str::ulid(),
            'stream' => 'schedule',
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_name' => 'System',
            'module' => 'cron',
            'action' => $status,
            'description' => $status === 'success' ? "Cron_{$taskKey}执行完成" : "Cron_{$taskKey}执行失败",
            'subject_type' => 'schedule_task',
            'subject_id' => null,
            'context' => json_encode(['task_key' => $taskKey, 'duration_ms' => $durationMs]),
            'ip_address' => null,
            'trace_id' => null,
            'created_at' => $createdAt->toDateTimeString(),
            'updated_at' => $createdAt->toDateTimeString(),
        ]);
    }
}
