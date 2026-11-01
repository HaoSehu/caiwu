<?php

namespace App\Services\System;

use App\Models\ScheduleRunLog;
use App\Services\Automation\ScheduleHookService;
use App\Support\ActivityLogStream;
use App\Support\CacheKey;
use App\Support\LikeEscape;
use App\Support\PayloadLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ScheduleRunLogService
{
    /** 活动镜像 summary 整体编码上限：超大结果数组降级为摘要，撑大 activity_logs 的元凶之一 */
    private const CRON_MIRROR_SUMMARY_MAX_BYTES = 4096;

    /** 镜像去重指纹存活期（2P1-A）：跨进程共享，过期仅导致一次原样落库，无正确性影响 */
    private const MIRROR_FINGERPRINT_TTL_SECONDS = 604800;

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
            // 2P1-A 镜像降频（2026-11-01）：成功且 summary 与上一次完全一致时跳过
            // 镜像行（首行/内容变化行/失败行照常落库），压缩 activity_logs 的
            // cron 模块重复载荷（实库 7 天窗口 582 行/任务只对应 1 种 summary）。
            if ($this->shouldSkipSuccessMirror($result, $context)) {
                $hookService->runMany([
                    ScheduleHookService::HOOK_TASK_AFTER,
                    ScheduleHookService::HOOK_AFTER_CRON,
                ], array_merge($hookContext, [
                    'duration_ms' => $durationMs,
                    'finished_at' => $finishedAt->toDateTimeString(),
                    'summary' => is_array($result) ? $result : null,
                ]));

                return $result;
            }
            // 指纹记忆在镜像行确认落库后执行（审查 P1-②）：行未落则不记忆，
            // 下一轮同 summary 原样落库，避免 TTL 窗口内镜像断档。
            $mirrorStored = $this->storeCronActivityLog(
                $taskName,
                'success',
                $durationMs,
                is_array($result) ? $result : null,
                null,
                $context,
            );

            $resultKey = $context['task_key'] ?? null;
            if ($mirrorStored && is_string($resultKey) && $resultKey !== '' && is_array($result) && $result !== []) {
                $this->rememberMirrorFingerprint($resultKey, $result);
            }

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

            // 失败打断前驱链（审查 P1-①）：清除成功指纹，失败后首次恢复成功
            // 重新落镜像行，读取方能区分「连续成功」与「失败后恢复」。
            $this->forgetMirrorFingerprint($context);

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

    /**
     * 成功镜像去重（2P1-A）：task_key 存在、summary 为数组、且与缓存的上一条
     * 成功镜像指纹完全一致时跳过整行镜像。任何条件不满足（无 task_key、
     * 非数组 summary、首次执行、内容变化）都照常落库。
     *
     * 只读指纹：写入由 rememberMirrorFingerprint 在镜像行落库成功后执行，
     * 避免「指纹已写、行未落」导致 TTL 窗口内镜像断档（审查 P1-②）。
     * 缓存故障降级为不跳过（原样落库），去重是优化不是正确性依赖。
     *
     * @param  array<string, mixed>  $context
     */
    private function shouldSkipSuccessMirror(mixed $result, array $context): bool
    {
        $taskKey = $context['task_key'] ?? null;
        if (! is_string($taskKey) || $taskKey === '' || ! is_array($result) || $result === []) {
            return false;
        }

        try {
            $previous = Cache::get(CacheKey::cronMirrorSummaryFingerprint($taskKey));
        } catch (Throwable) {
            return false;
        }

        return is_string($previous)
            && $previous === $this->mirrorSummaryFingerprint($result);
    }

    /**
     * 镜像行确认落库成功后才写入指纹。put 失败只影响下一轮去重（多落一行），
     * 无正确性影响，静默降级。
     */
    private function rememberMirrorFingerprint(string $taskKey, array $result): void
    {
        try {
            Cache::put(
                CacheKey::cronMirrorSummaryFingerprint($taskKey),
                $this->mirrorSummaryFingerprint($result),
                self::MIRROR_FINGERPRINT_TTL_SECONDS,
            );
        } catch (Throwable) {
            // 缓存故障：放弃本轮指纹记忆，下一轮原样落库。
        }
    }

    /**
     * 失败打断前驱链（审查 P1-①）：失败执行后清除成功指纹，
     * 让「失败后首次恢复成功」重新落镜像行，台账引用不再跨过失败行。
     */
    private function forgetMirrorFingerprint(array $context): void
    {
        $taskKey = $context['task_key'] ?? null;
        if (! is_string($taskKey) || $taskKey === '') {
            return;
        }

        try {
            Cache::forget(CacheKey::cronMirrorSummaryFingerprint($taskKey));
        } catch (Throwable) {
            // 缓存故障：指纹残留至 TTL 过期，最坏影响是恢复成功的镜像行延迟一轮。
        }
    }

    /**
     * 指纹必须与落库路径同口径：compactCronSummary 之后的最终 summary。
     *
     * @param  array<string, mixed>  $summary
     */
    private function mirrorSummaryFingerprint(array $summary): string
    {
        $encoded = json_encode(
            $this->compactCronSummary($summary),
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        return $encoded === false ? md5(serialize($this->compactCronSummary($summary))) : md5($encoded);
    }

    /**
     * 落库结果：true=镜像行已写入；false=表未就绪或写入失败（已记 warning）。
     * 上层据此决定是否记忆指纹（审查 P1-②：行未落则不记忆）。
     */
    private function storeCronActivityLog(
        string $taskName,
        string $status,
        int $durationMs,
        ?array $summary,
        ?string $errorMessage,
        array $context
    ): bool {
        if (! $this->activityLogTableIsReady()) {
            return false;
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

            return false;
        }

        return true;
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
