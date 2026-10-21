<?php

namespace App\Services\System;

use App\Models\ScheduleRunLog;
use App\Services\Automation\ScheduleHookService;
use App\Support\ActivityLogStream;
use App\Support\LikeEscape;
use App\Support\PayloadLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ScheduleRunLogService
{
    /** 活动镜像 summary 整体编码上限：超大结果数组降级为摘要，撑大 activity_logs 的元凶之一 */
    private const CRON_MIRROR_SUMMARY_MAX_BYTES = 4096;

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

            // V9-02 收敛（2026-10-10）：schedule_run_logs 已停写。每次执行只保留
            // 「心跳台账 schedule_task_runs + activity_logs 审计镜像」两层；
            // 存量 run_logs 行由日志中心只读展示并随归档自然清退。
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

            // V9-02 收敛：失败路径同样只落审计镜像，不再写 schedule_run_logs。
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

    private function scheduleRunLogTableIsReady(): bool
    {
        try {
            return Schema::hasTable((new ScheduleRunLog)->getTable());
        } catch (Throwable $exception) {
            Log::warning('[定时任务] 运行日志表检查失败，已跳过本次日志记录', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return false;
        }
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

    /**
     * 存量读源：V9-02 收敛（2026-10-10）停写后，schedule_run_logs 仅用于历史查看，
     * 新执行请读 activity_logs(module=cron) 审计镜像或心跳台账 schedule_task_runs。
     */
    public function getScheduleStatus(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        if (! $this->scheduleRunLogTableIsReady()) {
            return [
                'items' => [],
                'total' => 0,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => 1,
            ];
        }

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
                $q->where('task_name', 'like', LikeEscape::contains($keyword))
                    ->orWhere('error_msg', 'like', LikeEscape::contains($keyword));
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

    /**
     * cron 执行摘要随审计镜像写入 activity_logs.context；V9-02 收敛后任务日志视图
     * 直接消费该镜像，是执行结果的唯一数据库来源。
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
