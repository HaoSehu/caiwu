<?php

use App\Jobs\MassSendDispatchJob;
use App\Models\MassSend;
use App\Services\Care\CarePlanAutomationService;
use App\Services\Service\ServiceTransferService;
use Illuminate\Support\Facades\Schedule;

/**
 * 唯一时钟源：Laravel Schedule 每分钟驱动一次全局心跳与队列消费。
 * 心跳负责按 15 分钟槽位规则去重派发定时任务，不再内联消费队列；
 * 队列由独立的 queue:drain 后台消费，二者互不阻塞（对应参考系统 cron/task 分离）。
 */
$shouldUseScheduleMutex = ! (
    PHP_OS_FAMILY === 'Windows'
    && (string) config('cache.default', 'file') === 'file'
);

config([
    'idc.schedule_runtime' => array_replace_recursive(
        (array) config('idc.schedule_runtime', []),
        [
            'mutex' => [
                'enabled' => $shouldUseScheduleMutex,
                'degraded' => ! $shouldUseScheduleMutex,
                'mode' => $shouldUseScheduleMutex ? 'without_overlapping' : 'degraded',
                'reason' => $shouldUseScheduleMutex ? '' : 'windows_file_cache_lock_unreliable',
                'cache_store' => (string) config('cache.default', 'file'),
                'os_family' => PHP_OS_FAMILY,
            ],
            'automation_config' => [
                'status' => 'loaded',
                'fallback_reason' => '',
            ],
        ],
    ),
]);

$heartbeat = Schedule::command('scheduler:heartbeat')
    ->everyMinute()
    ->name('scheduler-heartbeat');

// 队列消费与心跳解耦：queue:drain 在后台子进程中运行，长任务不会阻塞
// schedule:run 与心跳派发；drain 锁保证同一队列不并发消费。
Schedule::command('queue:drain')
    ->everyMinute()
    ->name('queue-drain')
    ->runInBackground();

// 存活探针与心跳解耦：即使心跳命令抛异常，探针仍能发现并告警。
Schedule::command('scheduler:liveness')
    ->everyMinute()
    ->name('scheduler-liveness');

if ($shouldUseScheduleMutex) {
    $heartbeat->withoutOverlapping(2);
}

// ── 功能模块扩展（2026-10-11 设计方案）──

// M9 批量群发：每分钟扫描——待发送任务 scheduled_at 到点，及发送中超 1 小时的
// 卡死任务（调度 Job 丢失兜底续派）。
// MassSendDispatchJob 内部原子抢占 + MassSendChunkJob 逐 target 原子消费（status=0 抢占），
// 重复触发/续派安全不重发；立即发送（scheduled_at=NULL）由 start() 直接派发，本扫描兜底。
Schedule::call(function (): void {
    MassSend::query()
        ->where(function ($query): void {
            $query->where(function ($pending): void {
                $pending->where('status', MassSend::STATUS_PENDING)
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '<=', now());
            })->orWhere(function ($stuck): void {
                // 发送中超 1 小时才重扫，防止与运行中的分片 Job 重叠。
                $stuck->where('status', MassSend::STATUS_SENDING)
                    ->where('started_at', '<=', now()->subHour());
            });
        })
        ->orderBy('id')
        ->pluck('id')
        ->each(fn ($id) => MassSendDispatchJob::dispatch((int) $id));
})->everyMinute()->name('mass-send-dispatch')->withoutOverlapping();

// M12 客户关怀：每日 09:00 执行当日计划（生日/到期/召回）。
// run 的 unique(care_plan_id, planned_date) + 逐目标 cycle_key 唯一键保证幂等，重跑不重发。
// 注意：关怀 type=2 到期提醒与 BillingAutomationService::sendRenewNotices 的 7/3/1 天里程碑
// 并存会双发，上线二选一（建议 renew_notice_enabled=false 由关怀接管），见设计方案 M12 节。
Schedule::call(fn () => app(CarePlanAutomationService::class)->runForDate(now()))
    ->daily()->at('09:00')->name('care-plan-daily')->withoutOverlapping();

// M6 服务转移：到期未确认的转移批量置过期并通知双方。
Schedule::call(fn () => app(ServiceTransferService::class)->expireDue(now()))
    ->hourly()
    ->name('service-transfer-expire')
    ->withoutOverlapping(5);
