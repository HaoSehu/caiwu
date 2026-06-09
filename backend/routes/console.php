<?php

use App\Models\Supplier;
use App\Services\Automation\AutoRenewService;
use App\Services\Automation\BillingAutomationService;
use App\Services\Automation\InvoiceCleanupAutomationService;
use App\Services\Automation\ServiceLifecycleAutomationService;
use App\Services\Automation\ServiceStatusSyncService;
use App\Services\Finance\CouponCampaignService;
use App\Services\ProductCatalog\ProductCatalogService;
use App\Services\Referral\ReferralService;
use App\Services\System\ScheduleRunLogService;
use App\Services\System\SettingService;
use App\Services\Ticket\TicketAutomationService;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\ProviderResolver;
use App\Support\AutomationScheduleExpression;
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
        fwrite(STDOUT, $resolvedMessage.PHP_EOL);

        return;
    }

    echo $resolvedMessage.PHP_EOL;
};

$recordScheduleRun = static function (string $taskName, callable $callback) {
    return app(ScheduleRunLogService::class)->record($taskName, $callback);
};

$automationConfig = (static function () use ($updateScheduleRuntimeState): array {
    try {
        return app(SettingService::class)->getAutomationConfig();
    } catch (Throwable $exception) {
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

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('接口认证刷新', function () {
        $providerResolver = app(ProviderResolver::class);
        $suppliers = Supplier::enabled()->get();
        $summary = [
            'matched' => 0,
            'refreshed' => 0,
            'failed' => 0,
        ];

        foreach ($suppliers as $supplier) {
            $provider = $providerResolver->resolveForSupplier($supplier);
            if (! $provider->supports(ProvidesScheduledAuthRefresh::class)) {
                continue;
            }

            $summary['matched']++;

            try {
                $provider->require(ProvidesScheduledAuthRefresh::class, '当前供应商不支持认证刷新')
                    ->refreshJwt($supplier);
                $summary['refreshed']++;
            } catch (Throwable $e) {
                $summary['failed']++;

                Log::error('[定时任务] JWT刷新失败', [
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('[定时任务] 接口认证刷新执行完成', $summary);

        return $summary;
    });
    $writeScheduleOutput('接口认证刷新成功');
})->everyFifteenMinutes()->name('接口认证刷新'), 10);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('服务自动续费', function () {
        $summary = app(AutoRenewService::class)->handle(10);
        if (($summary['matched'] ?? 0) > 0) {
            Log::info('[定时任务] 自动续费执行完成', $summary);
        } else {
            Log::debug('[定时任务] 自动续费执行完成（无匹配）', $summary);
        }

        return $summary;
    });
    $writeScheduleOutput('服务自动续费刷新成功');
})->everyFiveMinutes()->name('服务自动续费'), 15);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('推荐奖励释放', function () {
        $released = app(ReferralService::class)->releaseMaturedRewards();
        if ($released > 0) {
            Log::info('[定时任务] 推荐奖励释放执行完成', ['released' => $released]);
        }

        return ['released' => $released];
    });
    $writeScheduleOutput('推荐奖励释放刷新成功');
})->everyTenMinutes()->name('推荐奖励释放'), 20);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('服务生命周期维护', function () {
        $summary = app(ServiceLifecycleAutomationService::class)->handle();
        if (array_sum($summary) > 0) {
            Log::info('[定时任务] 服务生命周期维护执行完成', $summary);
        }

        return $summary;
    });
    $writeScheduleOutput('服务生命周期维护刷新成功');
})->cron($resolveAutomationCron(
    'service_lifecycle_schedule_mode',
    'service_lifecycle_schedule_time',
    AutomationScheduleExpression::MODE_EVERY_FIVE_MINUTES,
    '00:05:00'
))->name('服务生命周期维护'), 15);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('用户产品状态同步', function () {
        $summary = app(ServiceStatusSyncService::class)->handle();
        Log::info('[定时任务] 用户产品状态同步执行完成', $summary);

        return $summary;
    });
    $writeScheduleOutput('用户产品状态同步刷新成功');
})->everyFifteenMinutes()->name('用户产品状态同步'), 30);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('账单自动化维护', function () {
        $summary = app(BillingAutomationService::class)->handle();
        Log::info('[定时任务] 账单自动化维护执行完成', $summary);

        return $summary;
    });
    $writeScheduleOutput('账单自动化维护刷新成功');
})->cron($resolveAutomationCron(
    'billing_maintenance_schedule_mode',
    'billing_maintenance_schedule_time',
    AutomationScheduleExpression::MODE_HOURLY,
    '00:00:00'
))->name('账单自动化维护'), 30);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('上游产品配置同步', function () {
        $summary = app(ProductCatalogService::class)->syncUpstreamProductConfigOptions();
        Log::info('[定时任务] 上游产品配置同步执行完成', $summary);

        return $summary;
    });
    $writeScheduleOutput('上游产品配置同步刷新成功');
})->daily()->name('上游产品配置同步'), 180);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('优惠券活动发放', function () {
        $summary = app(CouponCampaignService::class)->dispatchDueCampaigns();
        if (($summary['triggered'] ?? 0) > 0 || ($summary['failed'] ?? 0) > 0) {
            Log::info('[定时任务] 优惠券活动自动发放执行完成', $summary);
        }

        return $summary;
    });
    $writeScheduleOutput('优惠券活动发放刷新成功');
})->everyMinute()->name('优惠券活动发放'), 10);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('工单自动关闭', function () {
        $summary = app(TicketAutomationService::class)->handle();
        Log::info('[定时任务] 工单自动关闭执行完成', $summary);

        return $summary;
    });
    $writeScheduleOutput('工单自动关闭刷新成功');
})->cron($resolveAutomationCron(
    'ticket_auto_close_schedule_mode',
    'ticket_auto_close_schedule_time',
    AutomationScheduleExpression::MODE_HOURLY,
    '00:00:00'
))->name('工单自动关闭'), 20);

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('账单与充值清理', function () {
        $summary = app(InvoiceCleanupAutomationService::class)->handle();
        if (array_sum($summary) > 0) {
            Log::info('[定时任务] 账单与充值清理执行完成', $summary);
        }

        return $summary;
    });
    $writeScheduleOutput('账单与充值清理刷新成功');
})->cron($resolveAutomationCron(
    'order_cleanup_schedule_mode',
    'order_cleanup_schedule_time',
    AutomationScheduleExpression::MODE_EVERY_FIVE_MINUTES,
    '00:00:00'
))->name('账单与充值清理'), 15);

$applyScheduleMutex(Schedule::command('vnc:ensure-relay')
    ->everyMinute()->name('VNC Relay 守护'), 2);

$queueWorkerQueues = (string) config('queue.caiwu_worker_queues', 'provision,referral,notification,coupon,default');
$queueWorkerTries = (int) config('queue.caiwu_worker_tries', 3);
$queueWorkerTimeout = (int) config('queue.caiwu_worker_timeout', 1200);

$applyScheduleMutex(Schedule::command(
    "queue:work --queue={$queueWorkerQueues} --sleep=1 --tries={$queueWorkerTries} --timeout={$queueWorkerTimeout} --stop-when-empty --max-time=50"
)->everyMinute()->name('队列积压消费'), 2);
