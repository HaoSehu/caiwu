<?php

use App\Models\Supplier;
use App\Services\AutoRenewService;
use App\Services\BillingAutomationService;
use App\Services\CouponCampaignService;
use App\Services\MofangFinanceClient;
use App\Services\OrderCleanupAutomationService;
use App\Services\ReferralService;
use App\Services\ServiceLifecycleAutomationService;
use App\Services\SettingService;
use App\Services\ServiceStatusSyncService;
use App\Services\TicketAutomationService;
use App\Support\AutomationScheduleExpression;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

/**
 * Windows 下文件缓存的调度互斥锁容易因 Web 服务账号写入 storage 失败而报错，
 * 此时跳过 withoutOverlapping，避免整个 schedule:run 被中断。
 */
$shouldUseScheduleMutex = ! (
    PHP_OS_FAMILY === 'Windows'
    && (string) config('cache.default', 'file') === 'file'
);

config([
    'idc.schedule_runtime' => [
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
]);

$updateScheduleRuntimeState = static function (array $state): void {
    config([
        'idc.schedule_runtime' => array_replace_recursive(
            (array) config('idc.schedule_runtime', []),
            $state,
        ),
    ]);
};

$applyScheduleMutex = static function ($event, int $expiresMinutes = 15) use ($shouldUseScheduleMutex) {
    if ($shouldUseScheduleMutex) {
        $event->withoutOverlapping(max(1, $expiresMinutes));
    }

    return $event;
};

$writeScheduleOutput = static function (string $message): void {
    $resolvedMessage = trim($message);
    if ($resolvedMessage === '') {
        return;
    }

    if (defined('STDOUT')) {
        fwrite(STDOUT, $resolvedMessage . PHP_EOL);

        return;
    }

    echo $resolvedMessage . PHP_EOL;
};

$automationConfig = (static function () use ($updateScheduleRuntimeState): array {
    try {
        return app(SettingService::class)->getAutomationConfig();
    } catch (\Throwable $exception) {
        $updateScheduleRuntimeState([
            'automation_config' => [
                'status' => 'fallback_default',
                'fallback_reason' => trim((string) $exception->getMessage()),
            ],
        ]);

        Log::warning('[定时任务] 自动化配置读取失败，已回退到默认调度配置', [
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);

        return SettingService::defaultAutomationConfig();
    }
})();

$resolveAutomationCron = static function (
    string $modeKey,
    string $timeKey,
    string $defaultMode,
    string $defaultTime
) use ($automationConfig): string {
    return AutomationScheduleExpression::resolve(
        (string) ($automationConfig[$modeKey] ?? $defaultMode),
        (string) ($automationConfig[$timeKey] ?? $defaultTime),
        $defaultMode,
        $defaultTime,
    );
};

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput) {
    $client = app(MofangFinanceClient::class);
    $suppliers = Supplier::enabled()->get();
    $summary = [
        'matched' => 0,
        'refreshed' => 0,
        'failed' => 0,
    ];

    foreach ($suppliers as $supplier) {
        $summary['matched']++;

        try {
            $client->refreshJwt($supplier);
            $summary['refreshed']++;
        } catch (\Throwable $e) {
            $summary['failed']++;

            Log::error('[定时任务] JWT刷新失败', [
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    Log::info('[定时任务] JWT刷新执行完成', $summary);
    $writeScheduleOutput('魔方 JWT 刷新成功');
})->everyFifteenMinutes()->name('魔方 JWT 刷新'), 10);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput) {
    $summary = app(AutoRenewService::class)->handle(10);

    Log::info('[定时任务] 自动续费执行完成', $summary);
    $writeScheduleOutput('服务自动续费刷新成功');
})->everyFiveMinutes()->name('服务自动续费'), 15);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput) {
    app(ReferralService::class)->releaseMaturedRewards();
    Log::info('[定时任务] 推荐奖励释放执行完成');
    $writeScheduleOutput('推荐奖励释放刷新成功');
})->everyTenMinutes()->name('推荐奖励释放'), 20);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput) {
    $summary = app(ServiceLifecycleAutomationService::class)->handle();
    Log::info('[定时任务] 服务生命周期维护执行完成', $summary);
    $writeScheduleOutput('服务生命周期维护刷新成功');
})->cron($resolveAutomationCron(
    'service_lifecycle_schedule_mode',
    'service_lifecycle_schedule_time',
    AutomationScheduleExpression::MODE_EVERY_FIVE_MINUTES,
    '00:05:00'
))->name('服务生命周期维护'), 15);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput) {
    $summary = app(ServiceStatusSyncService::class)->handle();
    Log::info('[定时任务] 用户产品状态同步执行完成', $summary);
    $writeScheduleOutput('用户产品状态同步刷新成功');
})->everyFifteenMinutes()->name('用户产品状态同步'), 30);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput) {
    $summary = app(BillingAutomationService::class)->handle();
    Log::info('[定时任务] 账单自动化维护执行完成', $summary);
    $writeScheduleOutput('账单自动化维护刷新成功');
})->cron($resolveAutomationCron(
    'billing_maintenance_schedule_mode',
    'billing_maintenance_schedule_time',
    AutomationScheduleExpression::MODE_HOURLY,
    '00:00:00'
))->name('账单自动化维护'), 30);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput) {
    $summary = app(CouponCampaignService::class)->dispatchDueCampaigns();
    Log::info('[定时任务] 优惠券活动自动发放执行完成', $summary);
    $writeScheduleOutput('优惠券活动发放刷新成功');
})->everyMinute()->name('优惠券活动发放'), 10);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput) {
    $summary = app(TicketAutomationService::class)->handle();
    Log::info('[定时任务] 工单自动关闭执行完成', $summary);
    $writeScheduleOutput('工单自动关闭刷新成功');
})->cron($resolveAutomationCron(
    'ticket_auto_close_schedule_mode',
    'ticket_auto_close_schedule_time',
    AutomationScheduleExpression::MODE_HOURLY,
    '00:00:00'
))->name('工单自动关闭'), 20);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput) {
    $summary = app(OrderCleanupAutomationService::class)->handle();
    Log::info('[定时任务] 订单与充值清理执行完成', $summary);
    $writeScheduleOutput('订单与充值清理刷新成功');
})->cron($resolveAutomationCron(
    'order_cleanup_schedule_mode',
    'order_cleanup_schedule_time',
    AutomationScheduleExpression::MODE_EVERY_FIVE_MINUTES,
    '00:00:00'
))->name('订单与充值清理'), 15);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput) {
    $exitCode = Artisan::call('orders:sync-processing-status');

    if ($exitCode !== 0) {
        throw new RuntimeException('处理中订单状态同步执行失败，退出码：' . $exitCode);
    }

    $writeScheduleOutput('处理中订单状态同步刷新成功');
})->everyFiveMinutes()->name('处理中订单状态同步'), 10);
