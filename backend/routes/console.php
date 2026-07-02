<?php

use App\Models\Supplier;
use App\Services\Automation\AutoRenewService;
use App\Services\Automation\BillingAutomationService;
use App\Services\Automation\InvoiceCleanupAutomationService;
use App\Services\Automation\ScheduleHookService;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

$recordScheduleRun = static function (string $taskName, callable $callback, array $context = []) {
    return app(ScheduleRunLogService::class)->record($taskName, $callback, $context);
};

/**
 * 关键任务的业务层 advisory lock 兜底。
 *
 * 调度互斥启用时（withoutOverlapping 有效），直接执行回调，避免双重锁开销；
 * 调度互斥降级时（Windows + file 缓存等），用 Redis atomic lock 兜底防重入。
 * Redis 不可用时退化为直接执行，避免任务完全停摆——此时 ScheduleRunLogService
 * 已写入 mutex 降级 warning，运维可据日志察觉。
 */
$withAdvisoryLock = static function (string $lockKey, int $ttlSeconds, callable $callback) use ($shouldUseScheduleMutex, $writeScheduleOutput) {
    if ($shouldUseScheduleMutex) {
        return $callback();
    }

    try {
        $lock = Cache::store('redis')->lock("schedule:advisory:{$lockKey}", $ttlSeconds);
        if (! $lock->acquire()) {
            $writeScheduleOutput("{$lockKey} 已被其他进程持有，跳过本次执行");
            Log::info('[定时任务] Advisory lock 被占用，跳过本次执行', ['lock_key' => $lockKey]);

            return ['skipped' => true, 'reason' => 'advisory_lock_busy', 'lock_key' => $lockKey];
        }

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    } catch (Throwable $e) {
        Log::warning('[定时任务] Advisory lock 获取失败，降级直接执行', [
            'lock_key' => $lockKey,
            'error' => $e->getMessage(),
            'exception' => $e::class,
        ]);

        return $callback();
    }
};

$recordArtisanScheduleRun = static function (
    string $taskName,
    string $command,
    array $parameters = [],
    array $context = []
) use ($recordScheduleRun) {
    return $recordScheduleRun($taskName, function () use ($command, $parameters) {
        $exitCode = Artisan::call($command, $parameters);
        $output = trim(Artisan::output());

        $summary = [
            'command' => $command,
            'exit_code' => $exitCode,
        ];

        if ($parameters !== []) {
            $summary['parameters'] = $parameters;
        }

        if ($output !== '') {
            $summary['output'] = mb_substr($output, 0, 2000);
        }

        if ($exitCode !== 0) {
            $message = "Artisan command [{$command}] exited with code {$exitCode}.";
            if ($output !== '') {
                $message .= ' Output: '.mb_substr($output, 0, 1000);
            }

            throw new RuntimeException($message);
        }

        return $summary;
    }, array_merge([
        'command' => $command,
        'source' => 'scheduled_command',
    ], $context));
};

$registerScheduleHook = static function (
    string $taskKey,
    string $taskName,
    string $hook,
    callable $frequency,
    int $expiresMinutes = 5
) use ($applyScheduleMutex, $writeScheduleOutput): void {
    if (! app(ScheduleHookService::class)->hasListeners($hook)) {
        return;
    }

    $event = Schedule::call(function () use ($taskKey, $taskName, $hook, $writeScheduleOutput) {
        app(ScheduleRunLogService::class)->record($taskName, function () use ($taskKey, $hook) {
            $results = app(ScheduleHookService::class)->run($hook, [
                'task_key' => $taskKey,
                'hook' => $hook,
                'source' => 'schedule_tick',
            ]);

            return [
                'hook' => $hook,
                'listeners' => count($results),
                'results' => $results,
            ];
        }, [
            'task_key' => $taskKey,
            'hook' => $hook,
            'source' => 'schedule_tick',
        ]);

        $writeScheduleOutput($taskName.'执行完成');
    })->name($taskKey);

    $frequency($event);
    $applyScheduleMutex($event, $expiresMinutes);
};

$registerScheduleHook(
    'schedule-hook-every-minute',
    '调度扩展 Hook（每分钟）',
    ScheduleHookService::HOOK_EVERY_MINUTE,
    static fn ($event) => $event->everyMinute(),
    2
);

$registerScheduleHook(
    'schedule-hook-every-five-minutes',
    '调度扩展 Hook（每五分钟）',
    ScheduleHookService::HOOK_EVERY_FIVE_MINUTES,
    static fn ($event) => $event->everyFiveMinutes(),
    5
);

$registerScheduleHook(
    'schedule-hook-hourly',
    '调度扩展 Hook（每小时）',
    ScheduleHookService::HOOK_HOURLY,
    static fn ($event) => $event->hourly(),
    10
);

$registerScheduleHook(
    'schedule-hook-daily',
    '调度扩展 Hook（每日）',
    ScheduleHookService::HOOK_DAILY,
    static fn ($event) => $event->dailyAt('03:10'),
    30
);

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

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun, $withAdvisoryLock) {
    $recordScheduleRun('服务自动续费', function () use ($withAdvisoryLock) {
        return $withAdvisoryLock('service-auto-renew', 240, function () {
            $summary = app(AutoRenewService::class)->handle(60);
            if (($summary['matched'] ?? 0) > 0) {
                Log::info('[定时任务] 自动续费执行完成', $summary);
            } else {
                Log::debug('[定时任务] 自动续费执行完成（无匹配）', $summary);
            }

            return $summary;
        });
    });
    $writeScheduleOutput('服务自动续费刷新成功');
})->hourly()->name('服务自动续费'), 30);

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

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun, $withAdvisoryLock) {
    $recordScheduleRun('账单自动化维护', function () use ($withAdvisoryLock) {
        return $withAdvisoryLock('billing-maintenance', 1800, function () {
            $summary = app(BillingAutomationService::class)->handle();
            Log::info('[定时任务] 账单自动化维护执行完成', $summary);

            return $summary;
        });
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

$applyScheduleMutex(Schedule::call(function () use ($writeScheduleOutput, $recordScheduleRun) {
    $recordScheduleRun('日志归档预检', function () {
        $exitCode = Artisan::call('db:archive-logs', [
            '--dry-run' => true,
            '--json' => true,
        ]);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            $message = "Artisan command [db:archive-logs] exited with code {$exitCode}.";
            if ($output !== '') {
                $message .= ' Output: '.mb_substr($output, 0, 1000);
            }

            throw new RuntimeException($message);
        }

        $decoded = json_decode($output, true);
        $summary = is_array($decoded) ? [
            'exit_code' => $exitCode,
            'report_path' => $decoded['report_path'] ?? null,
            'eligible_rows' => $decoded['totals']['eligible_rows'] ?? 0,
            'tables' => array_map(
                static fn (array $table): array => [
                    'eligible_rows' => $table['eligible_rows'] ?? 0,
                    'cutoff' => $table['cutoff'] ?? null,
                ],
                (array) ($decoded['tables'] ?? [])
            ),
        ] : [
            'exit_code' => $exitCode,
            'output' => $output,
        ];

        Log::info('[定时任务] 日志归档 dry-run 完成', $summary);

        return $summary;
    });
    $writeScheduleOutput('日志归档 dry-run 完成');
})->monthlyOn(1, '03:30')->name('日志归档 dry-run'), 60);

$applyScheduleMutex(Schedule::call(function () use ($recordArtisanScheduleRun, $writeScheduleOutput, $withAdvisoryLock) {
    $withAdvisoryLock('reconcile-account-balance', 1800, function () use ($recordArtisanScheduleRun, $writeScheduleOutput) {
        $recordArtisanScheduleRun('账户余额在线对账', 'reconcile:account-balance', [], [
            'task_key' => 'reconcile-account-balance',
        ]);
        $writeScheduleOutput('账户余额在线对账执行完成');
    });
})->hourly()->name('账户余额在线对账'), 30);

// 上游开通失败孤儿单补偿：默认 dry-run 只告警，发现孤儿单时管理员据日志
// 核实上游实际开通状态后，用 `php artisan provision:retry-failed --execute` 手动重试。
$applyScheduleMutex(Schedule::call(function () use ($recordArtisanScheduleRun, $writeScheduleOutput) {
    $recordArtisanScheduleRun('上游开通孤儿单补偿告警', 'provision:retry-failed', [], [
        'task_key' => 'provision-retry-failed',
    ]);
    $writeScheduleOutput('上游开通孤儿单补偿告警执行完成');
})->everyTenMinutes()->name('上游开通孤儿单补偿告警'), 15);

$applyScheduleMutex(Schedule::call(function () use ($recordArtisanScheduleRun, $writeScheduleOutput) {
    $recordArtisanScheduleRun('VNC Relay 守护', 'vnc:ensure-relay', [], [
        'task_key' => 'vnc-ensure-relay',
    ]);
    $writeScheduleOutput('VNC Relay 守护执行完成');
})->everyMinute()->name('VNC Relay 守护'), 2);

$queueWorkerQueues = (string) config('queue.caiwu_worker_queues', 'provision,referral,notification,coupon,default');
$queueWorkerTries = (int) config('queue.caiwu_worker_tries', 3);
$queueWorkerTimeout = (int) config('queue.caiwu_worker_timeout', 1200);
$queueWorkerParameters = [
    '--queue' => $queueWorkerQueues,
    '--sleep' => 1,
    '--tries' => $queueWorkerTries,
    '--timeout' => $queueWorkerTimeout,
    '--stop-when-empty' => true,
    '--max-time' => 50,
];

$applyScheduleMutex(Schedule::call(function () use ($recordArtisanScheduleRun, $writeScheduleOutput, $queueWorkerParameters) {
    $recordArtisanScheduleRun('队列积压消费', 'queue:work', $queueWorkerParameters, [
        'task_key' => 'queue-backlog-drain',
    ]);
    $writeScheduleOutput('队列积压消费执行完成');
})->everyMinute()->name('队列积压消费'), 2);

// 首页与产品目录缓存预热：每 5 分钟刷新，保持缓存热度
$applyScheduleMutex(Schedule::call(function () use ($recordArtisanScheduleRun, $writeScheduleOutput) {
    $recordArtisanScheduleRun('首页缓存预热', 'app:warmup-site-cache', [], [
        'task_key' => 'site-cache-warmup',
    ]);
    $writeScheduleOutput('首页缓存预热执行完成');
})->everyFiveMinutes()->name('首页缓存预热'), 10);
