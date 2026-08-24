<?php

namespace App\Services\System;

use App\Models\ScheduleRunLog;
use App\Services\Automation\ScheduleHookService;
use App\Support\ActivityLogStream;
use App\Support\PayloadLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ScheduleRunLogService
{
    /** 活动镜像 summary 整体编码上限：超大结果数组降级为摘要，撑大 activity_logs 的元凶之一 */
    private const CRON_MIRROR_SUMMARY_MAX_BYTES = 4096;

    private ?bool $scheduleRunLogTableReady = null;

    private ?bool $activityLogTableReady = null;

    private static bool $mutexDegradedWarningEmitted = false;

    public function record(string $taskName, callable $callback, array $context = []): mixed
    {
        $this->warnIfMutexDegraded();

        $hookService = app(ScheduleHookService::class);

        $startedAt = Carbon::now();
        $startMs = (int) (microtime(true) * 1000);
        $hookContext = array_merge($context, [
            'task_name' => $taskName,
            'started_at' => $startedAt->toDateTimeString(),
        ]);

        $hookService->runMany([
            ScheduleHookService::HOOK_BEFORE_CRON,
            ScheduleHookService::HOOK_TASK_BEFORE,
        ], $hookContext);

        try {
            $result = $callback();
            $endMs = (int) (microtime(true) * 1000);
            $finishedAt = Carbon::now();
            $durationMs = $endMs - $startMs;

            $this->storeRunLog([
                'task_name' => $taskName,
                'status' => 'success',
                'duration_ms' => $durationMs,
                'summary' => is_array($result) ? $result : null,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
            ]);

            $this->storeCronActivityLog($taskName, 'success', $durationMs, is_array($result) ? $result : null, null, $context);

            $hookService->runMany([
                ScheduleHookService::HOOK_TASK_AFTER,
                ScheduleHookService::HOOK_AFTER_CRON,
            ], array_merge($hookContext, [
                'duration_ms' => $durationMs,
                'finished_at' => $finishedAt->toDateTimeString(),
                'summary' => is_array($result) ? $result : null,
            ]));

            return $result;
        } catch (Throwable $e) {
            $endMs = (int) (microtime(true) * 1000);
            $finishedAt = Carbon::now();
            $durationMs = $endMs - $startMs;

            $this->storeRunLog([
                'task_name' => $taskName,
                'status' => 'failed',
                'duration_ms' => $durationMs,
                'error_msg' => mb_substr($e->getMessage(), 0, 2000),
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
            ]);

            $this->storeCronActivityLog($taskName, 'failed', $durationMs, null, $e->getMessage(), $context);

            $hookService->runMany([
                ScheduleHookService::HOOK_TASK_FAILED,
                ScheduleHookService::HOOK_AFTER_CRON,
            ], array_merge($hookContext, [
                'duration_ms' => $durationMs,
                'finished_at' => $finishedAt->toDateTimeString(),
                'error_message' => $e->getMessage(),
                'exception_class' => $e::class,
            ]));

            throw $e;
        }
    }

    private function storeCronActivityLog(
        string $taskName,
        string $status,
        int $durationMs,
        ?array $summary,
        ?string $errorMessage,
        array $context
    ): void {
        if (! $this->activityLogTableIsReady()) {
            return;
        }

        try {
            $description = $status === 'success'
                ? "Cron_{$taskName}执行完成"
                : "Cron_{$taskName}执行失败：".mb_substr((string) $errorMessage, 0, 500);

            app(ActivityLogService::class)->logSystem(
                'cron',
                $status,
                $description,
                'schedule_task',
                null,
                array_filter([
                    'task_key' => $context['task_key'] ?? null,
                    'source' => $context['source'] ?? null,
                    'duration_ms' => $durationMs,
                    'summary' => $this->compactCronSummary($summary),
                    'error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 2000) : null,
                ], static fn ($value): bool => $value !== null),
                ActivityLogStream::SCHEDULE,
            );
        } catch (Throwable $exception) {
            Log::warning('[定时任务] Cron 活动日志写入失败，已跳过本次日志记录', [
                'task_name' => $taskName,
                'status' => $status,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
    }

    private function storeRunLog(array $attributes): void
    {
        if (! $this->scheduleRunLogTableIsReady()) {
            return;
        }

        try {
            ScheduleRunLog::query()->create($attributes);
        } catch (Throwable $exception) {
            Log::warning('[定时任务] 运行日志写入失败，已跳过本次日志记录', [
                'task_name' => $attributes['task_name'] ?? null,
                'status' => $attributes['status'] ?? null,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
    }

    private function scheduleRunLogTableIsReady(): bool
    {
        if ($this->scheduleRunLogTableReady !== null) {
            return $this->scheduleRunLogTableReady;
        }

        try {
            $this->scheduleRunLogTableReady = Schema::hasTable((new ScheduleRunLog)->getTable());
        } catch (Throwable $exception) {
            Log::warning('[定时任务] 运行日志表检查失败，已跳过本次日志记录', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            $this->scheduleRunLogTableReady = false;
        }

        return $this->scheduleRunLogTableReady;
    }

    private function activityLogTableIsReady(): bool
    {
        if ($this->activityLogTableReady !== null) {
            return $this->activityLogTableReady;
        }

        try {
            $this->activityLogTableReady = Schema::hasTable('activity_logs');
        } catch (Throwable $exception) {
            Log::warning('[定时任务] 活动日志表检查失败，已跳过本次日志记录', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            $this->activityLogTableReady = false;
        }

        return $this->activityLogTableReady;
    }

    /**
     * 调度互斥处于降级模式（Windows + file 缓存等场景）时，每个 schedule:run
     * 进程首次进入 record() 写一次 warning，便于在 laravel.log 中察觉潜在重入风险。
     * 静态标志位保证单进程内只告警一次，避免每分钟刷屏。
     */
    private function warnIfMutexDegraded(): void
    {
        if (self::$mutexDegradedWarningEmitted) {
            return;
        }

        $mutex = (array) config('idc.schedule_runtime.mutex', []);
        if (! (bool) ($mutex['degraded'] ?? false)) {
            return;
        }

        self::$mutexDegradedWarningEmitted = true;

        Log::warning('[定时任务] 调度互斥处于降级模式，withoutOverlapping 已跳过，存在重入风险', [
            'reason' => $mutex['reason'] ?? '',
            'cache_store' => $mutex['cache_store'] ?? '',
            'os_family' => $mutex['os_family'] ?? '',
            'mode' => $mutex['mode'] ?? '',
        ]);
    }

    public function getScheduleStatus(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $query = ScheduleRunLog::query()->orderByDesc('id');

        if (! empty($filters['task_name'])) {
            $query->where('task_name', $filters['task_name']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['keyword'])) {
            $keyword = $filters['keyword'];
            $query->where(function ($q) use ($keyword) {
                $q->where('task_name', 'like', "%{$keyword}%")
                    ->orWhere('error_msg', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function getHealthOverview(): array
    {
        $tasks = ScheduleRunLog::query()
            ->selectRaw('task_name, MAX(finished_at) as last_run_at, COUNT(*) as total_runs')
            ->selectRaw('SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed_count')
            ->selectRaw('AVG(duration_ms) as avg_duration_ms')
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('task_name')
            ->get();

        return $tasks->map(function ($task) {
            $lastRun = $task->last_run_at ? Carbon::parse($task->last_run_at) : null;
            $minutesSinceLastRun = $lastRun ? (int) $lastRun->diffInMinutes(now()) : null;

            return [
                'task_name' => $task->task_name,
                'last_run_at' => $lastRun?->toDateTimeString(),
                'minutes_since_last_run' => $minutesSinceLastRun,
                'health' => $this->evaluateHealth($minutesSinceLastRun),
                'total_runs_24h' => (int) $task->total_runs,
                'failed_count_24h' => (int) $task->failed_count,
                'avg_duration_ms' => (int) round($task->avg_duration_ms),
            ];
        })->all();
    }

    private function evaluateHealth(?int $minutesSinceLastRun): string
    {
        if ($minutesSinceLastRun === null) {
            return 'unknown';
        }

        if ($minutesSinceLastRun <= 30) {
            return 'healthy';
        }

        if ($minutesSinceLastRun <= 120) {
            return 'warning';
        }

        return 'critical';
    }

    /**
     * cron summary 只在 schedule_run_logs 缺失时作为活动镜像 fallback 展示。
     * 复用统一的 PayloadLimiter::limit：叶子先限宽，整体编码超限时降级为
     * 「截断标记 + 原始字节 + 哈希 + 预览」，不再把整段结果塞进 activity_logs.context。
     *
     * @param  array<string, mixed>|null  $summary
     * @return array<string, mixed>|null
     */
    private function compactCronSummary(?array $summary): ?array
    {
        if ($summary === null) {
            return null;
        }

        return PayloadLimiter::limit(
            $summary,
            PayloadLimiter::DEFAULT_LEAF_MAX_BYTES,
            self::CRON_MIRROR_SUMMARY_MAX_BYTES,
            self::CRON_MIRROR_SUMMARY_MAX_BYTES,
        );
    }
}
