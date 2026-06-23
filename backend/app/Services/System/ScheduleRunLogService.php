<?php

namespace App\Services\System;

use App\Models\ScheduleRunLog;
use App\Services\Automation\ScheduleHookService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ScheduleRunLogService
{
    private ?bool $scheduleRunLogTableReady = null;

    private static bool $mutexDegradedWarningEmitted = false;

    public function record(string $taskName, callable $callback, array $context = []): mixed
    {
        $this->warnIfMutexDegraded();

        $startedAt = Carbon::now();
        $startMs = (int) (microtime(true) * 1000);
        $hookContext = array_merge($context, [
            'task_name' => $taskName,
            'started_at' => $startedAt->toDateTimeString(),
        ]);

        app(ScheduleHookService::class)->run(ScheduleHookService::HOOK_TASK_BEFORE, $hookContext);

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

            app(ScheduleHookService::class)->run(ScheduleHookService::HOOK_TASK_AFTER, array_merge($hookContext, [
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

            app(ScheduleHookService::class)->run(ScheduleHookService::HOOK_TASK_FAILED, array_merge($hookContext, [
                'duration_ms' => $durationMs,
                'finished_at' => $finishedAt->toDateTimeString(),
                'error_message' => $e->getMessage(),
                'exception_class' => $e::class,
            ]));

            throw $e;
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
}
