<?php

declare(strict_types=1);

namespace App\Services\Automation\Heartbeat;

use App\Jobs\RunHeartbeatTaskJob;
use App\Models\ScheduleTick;
use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\Data\TickContext;
use App\Services\Automation\Heartbeat\Data\TickSummary;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HeartbeatScheduler
{
    public function __construct(
        private ScheduleTickRepository $ticks,
        private ScheduleTaskRunRepository $taskRuns,
        private HeartbeatTaskRegistry $registry,
        private TriggerRuleMatcher $matcher,
        private QueueDrainService $queueDrain,
    ) {}

    public function tick(CarbonImmutable $now): TickSummary
    {
        $slot = TickSlot::floorToFifteenMinutes($now);
        $tickModel = $this->ticks->firstOrCreateSlot($now);
        $tick = $this->ticks->toContext($tickModel);
        // 锁 TTL 与槽位周期一致，避免极端卡顿下同槽位重入窗口。
        $lock = Cache::lock('scheduler:heartbeat:'.$slot->format('YmdHi'), 900);

        if (! $lock->get()) {
            Log::warning('[调度] 心跳槽位锁被占用，本槽位跳过派发，仅排空队列', [
                'slot' => $slot->format('YmdHi'),
                'tick_id' => $tick->id,
                'lock' => 'scheduler:heartbeat:'.$slot->format('YmdHi'),
            ]);

            return new TickSummary($tick, [], [], [], $this->safeDrain());
        }

        $queued = [];
        $skipped = [];
        $duplicates = [];
        $reclaimed = 0;
        $dispatchFailed = 0;

        try {
            foreach ($this->registry->enabledTasks() as $task) {
                $this->dispatchTaskForTick($task, $tickModel, $tick, $queued, $skipped, $duplicates, $reclaimed, $dispatchFailed);
            }
        } finally {
            $lock->release();
        }

        $this->reportHealth($slot, $queued, $skipped, $duplicates, $reclaimed, $dispatchFailed);

        return new TickSummary($tick, $queued, $skipped, $duplicates, $this->safeDrain());
    }

    /**
     * @param  list<string>  $queued
     * @param  list<string>  $skipped
     * @param  list<string>  $duplicates
     */
    private function dispatchTaskForTick(
        ScheduledTask $task,
        ScheduleTick $tickModel,
        TickContext $tick,
        array &$queued,
        array &$skipped,
        array &$duplicates,
        int &$reclaimed,
        int &$dispatchFailed,
    ): void {
        try {
            $matched = $this->matcher->firstMatchedRule($task->triggers(), $tick);
        } catch (\Throwable $exception) {
            $skipped[] = $task->key();
            Log::error('[心跳定时任务] 任务触发规则评估失败，已跳过本槽位', [
                'task' => $task->key(),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return;
        }

        if ($matched === null) {
            $skipped[] = $task->key();

            return;
        }

        $taskLock = $this->acquireTaskTriggerLock($task);
        if ($taskLock === null) {
            $duplicates[] = $task->key();

            return;
        }

        try {
            // 只有超过任务执行、队列可见性和锁安全余量的记录才回收，避免误杀仍在运行的任务。
            $reclaimed += $this->taskRuns->reclaimStaleRunsForTask($task->key(), $this->taskLeaseSeconds($task));

            if ($this->taskRuns->activeRunForTask($task->key()) !== null) {
                $duplicates[] = $task->key();

                return;
            }

            $run = $this->taskRuns->markQueued($tickModel, $task, $matched);
            if ($run === null) {
                $duplicates[] = $task->key();

                return;
            }

            try {
                RunHeartbeatTaskJob::dispatch(
                    $task->key(),
                    (int) $tickModel->id,
                    (int) $run->id,
                    null,
                    'heartbeat',
                    $task->timeout(),
                )->onQueue($task->queue());
                $queued[] = $task->key();
            } catch (\Throwable $exception) {
                $this->taskRuns->markDispatchFailed((int) $run->id, '队列派发失败：'.$exception->getMessage());
                $dispatchFailed++;

                Log::error('[心跳定时任务] 队列派发失败，将在同槽后续心跳重派', [
                    'task' => $task->key(),
                    'tick_id' => $tickModel->id,
                    'task_run_id' => $run->id,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        } catch (\Throwable $exception) {
            $skipped[] = $task->key();
            Log::error('[心跳定时任务] 任务注册或派发处理失败，已隔离本任务', [
                'task' => $task->key(),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        } finally {
            $taskLock->release();
        }
    }

    private function acquireTaskTriggerLock(ScheduledTask $task): ?Lock
    {
        try {
            $lock = Cache::lock(
                'scheduler:task-trigger:'.$task->key(),
                max(30, min(3600, $task->lockTtlSeconds())),
            );
            if (! $lock->get()) {
                return null;
            }

            return $lock;
        } catch (\Throwable $exception) {
            Log::error('[心跳定时任务] 获取任务并发锁失败，已跳过本槽位', [
                'task' => $task->key(),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function taskLeaseSeconds(ScheduledTask $task): int
    {
        $driver = (string) config('queue.default', 'sync');
        $retryAfter = (int) config("queue.connections.{$driver}.retry_after", 0);

        return max(60, $task->timeout() + 60, $task->lockTtlSeconds() + 60, $retryAfter + 60);
    }

    /**
     * 滞留运行、派发失败或队列积压出现时输出结构化告警；正常槽位保持静默。
     *
     * @param  list<string>  $queued
     * @param  list<string>  $skipped
     * @param  list<string>  $duplicates
     */
    private function reportHealth(
        CarbonImmutable $slot,
        array $queued,
        array $skipped,
        array $duplicates,
        int $reclaimed,
        int $dispatchFailed,
    ): void {
        $pendingJobs = $this->pendingJobsCount();

        if ($reclaimed === 0 && $dispatchFailed === 0 && $pendingJobs <= 0) {
            return;
        }

        Log::warning('[调度] 心跳槽位健康告警', [
            'slot' => $slot->format('YmdHi'),
            'queued' => count($queued),
            'skipped' => count($skipped),
            'duplicates' => count($duplicates),
            'reclaimed' => $reclaimed,
            'dispatch_failed' => $dispatchFailed,
            'jobs_table_pending' => $pendingJobs,
        ]);
    }

    private function pendingJobsCount(): int
    {
        try {
            if (! Schema::hasTable('jobs')) {
                return 0;
            }

            // 只统计自动化调度队列，业务队列的正常积压不触发调度健康告警。
            return DB::table('jobs')
                ->where('queue', (string) config('queue.caiwu_schedule_queue', 'automation'))
                ->whereNull('reserved_at')
                ->where('available_at', '<=', now()->getTimestamp())
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<string, mixed> */
    private function safeDrain(): array
    {
        try {
            return $this->queueDrain->drainOnceIfDatabaseQueue();
        } catch (\Throwable $exception) {
            Log::error('[调度] 队列消费失败，心跳结果仍保留', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return [
                'status' => 'failed',
                'reason' => 'queue_drain_failed',
                'message' => $exception->getMessage(),
            ];
        }
    }
}
