<?php

namespace App\Services;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernelContract;
use App\Support\AutomationScheduleExpression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SplFileObject;

class ScheduleTaskService
{
    public function __construct(
        private SettingService $settingService,
        private ScheduleTaskTriggerService $scheduleTaskTriggerService,
    ) {}
    private const TASK_META = [
        'refresh-mofang-jwt' => [
            'title' => '魔方 JWT 刷新',
            'description' => '定时刷新供应商 JWT 会话，减少上游接口因登录态过期导致的请求失败。',
            'category' => '供应商接口',
            'log_keywords' => ['JWT刷新', 'refresh-mofang-jwt'],
        ],
        'service-auto-renew' => [
            'title' => '服务自动续费',
            'description' => '扫描开启自动续费的服务，余额充足时自动创建续费订单并完成支付处理。',
            'category' => '服务续费',
            'log_keywords' => ['自动续费执行完成', '[自动续费]', 'service-auto-renew'],
        ],
        'referral-release-rewards' => [
            'title' => '推荐奖励释放',
            'description' => '把已过冻结期的推荐奖励转入可提现余额，并记录推荐账户流水。',
            'category' => '推荐奖励',
            'log_keywords' => ['推荐奖励释放执行完成', '推荐奖励', 'referral-release-rewards'],
        ],
        'service-lifecycle-maintenance' => [
            'title' => '服务生命周期维护',
            'description' => '处理服务到期暂停、暂停通知和到期后自动取消。',
            'category' => '服务生命周期',
            'log_keywords' => ['服务生命周期维护执行完成', 'service-lifecycle-maintenance'],
        ],
        'service-status-sync' => [
            'title' => '用户产品状态同步',
            'description' => '定时拉取上游实例详情与运行状态，并同步回本地用户服务状态。',
            'category' => '服务状态',
            'log_keywords' => ['用户产品状态同步执行完成', 'service-status-sync'],
        ],
        'billing-maintenance' => [
            'title' => '账单自动化维护',
            'description' => '处理续费提醒、自动建单、账单到期提醒和逾期标记。',
            'category' => '账单提醒',
            'log_keywords' => ['账单自动化维护执行完成', 'billing-maintenance'],
        ],
        'coupon-campaign-dispatch' => [
            'title' => '优惠券活动发放',
            'description' => '按活动配置的星期与时间自动生成一批公开优惠券，例如每周五 18:00 发放周五特惠。',
            'category' => '营销活动',
            'log_keywords' => ['优惠券活动自动发放执行完成', 'coupon-campaign-dispatch'],
        ],
        'ticket-auto-close' => [
            'title' => '工单自动关闭',
            'description' => '关闭超过阈值且长期无客户回复的工单。',
            'category' => '工单管理',
            'log_keywords' => ['工单自动关闭执行完成', 'ticket-auto-close'],
        ],
        'order-cleanup' => [
            'title' => '订单与充值清理',
            'description' => '自动取消超时未付款订单，并失效超时未付款充值单。',
            'category' => '订单清理',
            'log_keywords' => ['订单与充值清理执行完成', 'order-cleanup'],
        ],
        'sync-processing-order-status' => [
            'title' => '处理中订单状态同步',
            'description' => '定时校准开通中的订单状态，服务已激活时自动将订单更新为已完成。',
            'category' => '订单状态',
            'log_keywords' => ['处理中订单状态同步执行完成', 'sync-processing-order-status', 'orders:sync-processing-status'],
        ],
    ];

    public function overview(): array
    {
        $schedule = $this->resolveSchedule();
        $taskLogs = $this->loadTaskLogs();
        $recentLogs = collect($taskLogs)->take(24)->values()->all();
        $jobsTableReady = Schema::hasTable('jobs');
        $failedJobsTableReady = Schema::hasTable('failed_jobs');
        $appTimezone = (string) config('app.timezone', date_default_timezone_get());
        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');
        $runtimeState = $this->resolveScheduleRuntimeState();

        $tasks = collect($schedule->events())
            ->values()
            ->map(function ($event, int $index) use ($appTimezone) {
                $eventDescription = trim((string) ($event->description ?? ''));
                $summary = method_exists($event, 'getSummaryForDisplay')
                    ? trim((string) $event->getSummaryForDisplay())
                    : $eventDescription;
                $taskKey = $this->resolveTaskKeyFromEvent($eventDescription, $summary);
                $meta = self::TASK_META[$taskKey] ?? $this->buildFallbackTaskMeta($taskKey, $summary);
                $resolvedTaskKey = $taskKey !== '' ? $taskKey : 'schedule-task-' . ($index + 1);

                $lastLog = $this->findLatestTaskLog($taskKey);

                return [
                    'key' => $resolvedTaskKey,
                    'title' => $meta['title'],
                    'category' => $meta['category'],
                    'description' => $meta['description'],
                    'manual_triggerable' => $this->scheduleTaskTriggerService->supports($resolvedTaskKey),
                    'expression' => (string) $event->expression,
                    'summary' => $summary !== '' ? $summary : $resolvedTaskKey,
                    'timezone' => (string) ($event->timezone ?: $appTimezone),
                    'next_run_at' => $event->nextRunDate('now')->format('Y-m-d H:i:s'),
                    'without_overlapping' => (bool) $event->withoutOverlapping,
                    'run_in_background' => (bool) $event->runInBackground,
                    'overlap_expires_minutes' => (int) ($event->expiresAt ?? 0),
                    'last_log' => $lastLog,
                ];
            })
            ->values()
            ->all();

        return [
            'environment' => [
                'app_env' => (string) config('app.env', 'production'),
                'app_timezone' => $appTimezone,
                'php_binary' => $phpBinary,
                'artisan_path' => $artisanPath,
                'schedule_source' => base_path('routes/console.php'),
                'queue_driver' => (string) config('queue.default', 'sync'),
                'jobs_table_ready' => $jobsTableReady,
                'failed_jobs_table_ready' => $failedJobsTableReady,
                'pending_jobs' => $jobsTableReady ? (int) DB::table('jobs')->count() : null,
                'failed_jobs' => $failedJobsTableReady ? (int) DB::table('failed_jobs')->count() : null,
                'queue_runtime_mode' => $this->resolveQueueRuntimeMode($jobsTableReady),
                'schedule_mutex' => $runtimeState['mutex'],
                'automation_config' => $runtimeState['automation_config'],
            ],
            'commands' => $this->buildCommands($phpBinary, $artisanPath),
            'tasks' => $tasks,
            'recent_logs' => $recentLogs,
            'settings_snapshot' => $this->buildSettingsSnapshot(),
        ];
    }

    private function resolveScheduleRuntimeState(): array
    {
        $runtimeState = (array) config('idc.schedule_runtime', []);
        $cacheStore = trim((string) ($runtimeState['mutex']['cache_store'] ?? config('cache.default', 'file')));
        $osFamily = trim((string) ($runtimeState['mutex']['os_family'] ?? PHP_OS_FAMILY));
        $mutexEnabled = (bool) ($runtimeState['mutex']['enabled'] ?? ! ($osFamily === 'Windows' && $cacheStore === 'file'));
        $mutexDegraded = (bool) ($runtimeState['mutex']['degraded'] ?? ! $mutexEnabled);
        $mutexMode = trim((string) ($runtimeState['mutex']['mode'] ?? ($mutexEnabled ? 'without_overlapping' : 'degraded')));
        $mutexReason = trim((string) ($runtimeState['mutex']['reason'] ?? ''));
        $automationStatus = trim((string) ($runtimeState['automation_config']['status'] ?? 'loaded'));
        $fallbackReason = trim((string) ($runtimeState['automation_config']['fallback_reason'] ?? ''));

        return [
            'mutex' => [
                'enabled' => $mutexEnabled,
                'degraded' => $mutexDegraded,
                'mode' => $mutexMode !== '' ? $mutexMode : ($mutexEnabled ? 'without_overlapping' : 'degraded'),
                'reason' => $mutexReason,
                'cache_store' => $cacheStore,
                'os_family' => $osFamily,
            ],
            'automation_config' => [
                'status' => $automationStatus !== '' ? $automationStatus : 'loaded',
                'fallback_reason' => $fallbackReason,
            ],
        ];
    }

    private function buildCommands(string $phpBinary, string $artisanPath): array
    {
        $quotedPhp = $this->quoteShellArgument($phpBinary);
        $quotedArtisan = $this->quoteShellArgument($artisanPath);

        return [
            [
                'key' => 'schedule_run',
                'title' => '调度入口',
                'description' => '请设置以下命令每 1 分钟运行一次。',
                'command' => "{$quotedPhp} {$quotedArtisan} schedule:run",
            ],
            [
                'key' => 'schedule_work',
                'title' => '本地调度 Worker',
                'description' => '本地开发环境可常驻运行以下命令，无需额外系统 Cron。',
                'command' => "{$quotedPhp} {$quotedArtisan} schedule:work",
            ],
            [
                'key' => 'queue_work',
                'title' => '队列 Worker',
                'description' => '请在服务器上常驻运行以下命令（需 Supervisor 或 systemd 守护）。',
                'command' => "{$quotedPhp} {$quotedArtisan} queue:work --queue=referral,provision,default --sleep=3 --tries=3",
            ],
        ];
    }

    private function resolveSchedule(): Schedule
    {
        app(ConsoleKernelContract::class)->bootstrap();

        return app(Schedule::class);
    }

    private function loadTaskLogs(int $lineSample = 2000): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (! is_file($logPath)) {
            return [];
        }

        return collect($this->readLastLines($logPath, $lineSample))
            ->reverse()
            ->map(fn (string $line) => $this->parseLogLine($line))
            ->filter(fn (?array $item) => is_array($item) && $this->resolveTaskKeyFromMessage((string) $item['message']) !== null)
            ->values()
            ->all();
    }

    private function buildFallbackTaskMeta(string $taskKey, string $summary): array
    {
        $title = trim($summary) !== '' ? trim($summary) : trim($taskKey);

        return [
            'title' => $title !== '' ? $title : '未命名调度任务',
            'description' => '已在 Laravel Schedule 中注册，当前页面按运行时调度配置实时展示。',
            'category' => '系统调度',
        ];
    }

    private function resolveTaskKeyFromEvent(string $description, string $summary): string
    {
        $description = trim($description);
        $summary = trim($summary);

        if ($description !== '' && isset(self::TASK_META[$description])) {
            return $description;
        }

        foreach (self::TASK_META as $taskKey => $meta) {
            $title = trim((string) ($meta['title'] ?? ''));
            if ($title !== '' && ($description === $title || $summary === $title)) {
                return $taskKey;
            }
        }

        return $description !== '' ? $description : $summary;
    }

    private function findLatestTaskLog(string $taskKey): ?array
    {
        $taskKey = trim($taskKey);
        if ($taskKey === '' || ! isset(self::TASK_META[$taskKey])) {
            return null;
        }

        $logPath = storage_path('logs/laravel.log');
        if (! is_file($logPath)) {
            return null;
        }

        $keywords = array_values(array_filter((array) (self::TASK_META[$taskKey]['log_keywords'] ?? [])));
        if ($keywords === []) {
            return null;
        }

        foreach (array_reverse($this->readLastLines($logPath, 20000)) as $line) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($line, $keyword)) {
                    $parsed = $this->parseLogLine($line);
                    if (is_array($parsed) && ($parsed['task_key'] ?? null) === $taskKey) {
                        return $parsed;
                    }
                }
            }
        }

        return null;
    }

    private function parseLogLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        if (! preg_match('/^\[(?<time>[^\]]+)\]\s+\w+\.(?<level>[A-Z]+):\s+(?<message>.+)$/u', $line, $matches)) {
            return null;
        }

        $message = trim((string) $matches['message']);
        $taskKey = $this->resolveTaskKeyFromMessage($message);

        if ($taskKey === null) {
            return null;
        }

        $displayMessage = preg_replace('/\s+\{.*$/u', '', $message) ?: $message;

        return [
            'time' => trim((string) $matches['time']),
            'level' => trim((string) $matches['level']),
            'message' => $displayMessage,
            'raw' => $message,
            'task_key' => $taskKey,
        ];
    }

    private function resolveTaskKeyFromMessage(string $message): ?string
    {
        foreach (self::TASK_META as $taskKey => $meta) {
            foreach ((array) ($meta['log_keywords'] ?? []) as $keyword) {
                if ($keyword !== '' && str_contains($message, $keyword)) {
                    return $taskKey;
                }
            }
        }

        return null;
    }

    private function readLastLines(string $path, int $limit): array
    {
        $file = new SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $startLine = max($lastLine - $limit, 0);
        $lines = [];

        $file->seek($startLine);

        while (! $file->eof()) {
            $line = rtrim((string) $file->current(), "\r\n");
            if ($line !== '') {
                $lines[] = $line;
            }
            $file->next();
        }

        return $lines;
    }

    private function resolveQueueRuntimeMode(bool $jobsTableReady): string
    {
        $driver = (string) config('queue.default', 'sync');

        return match ($driver) {
            'database' => $jobsTableReady ? 'database_queue' : 'after_response_fallback',
            'redis' => 'redis_queue',
            'sync' => 'sync_inline',
            default => $driver,
        };
    }

    private function quoteShellArgument(string $value): string
    {
        return '"' . str_replace('"', '\"', $value) . '"';
    }

    private function buildSettingsSnapshot(): array
    {
        $config = $this->settingService->getAutomationConfig();

        return [
            [
                'label' => '到期自动暂停',
                'value' => $config['expire_suspend_enabled'] ? '已开启' : '已关闭',
                'note' => $config['expire_suspend_enabled']
                    ? "到期后 {$config['expire_suspend_after_days']} 天暂停，任务周期："
                        . AutomationScheduleExpression::describe(
                            (string) $config['service_lifecycle_schedule_mode'],
                            (string) $config['service_lifecycle_schedule_time'],
                            AutomationScheduleExpression::MODE_EVERY_FIVE_MINUTES,
                            '00:05:00'
                        )
                    : '不会自动暂停到期服务',
            ],
            [
                'label' => '续费提醒',
                'value' => $config['renew_notice_enabled'] ? '已开启' : '已关闭',
                'note' => $config['renew_notice_enabled']
                    ? '提醒天数：' . implode(' / ', $config['renew_notice_days_before']) . ' 天前，任务周期：'
                        . AutomationScheduleExpression::describe(
                            (string) $config['billing_maintenance_schedule_mode'],
                            (string) $config['billing_maintenance_schedule_time'],
                            AutomationScheduleExpression::MODE_HOURLY,
                            '00:00:00'
                        )
                    : '不会主动发送续费提醒',
            ],
            [
                'label' => '工单自动关闭',
                'value' => $config['ticket_auto_close_enabled'] ? '已开启' : '已关闭',
                'note' => $config['ticket_auto_close_enabled']
                    ? "员工回复后 {$config['ticket_auto_close_after_hours']} 小时自动关闭，任务周期："
                        . AutomationScheduleExpression::describe(
                            (string) $config['ticket_auto_close_schedule_mode'],
                            (string) $config['ticket_auto_close_schedule_time'],
                            AutomationScheduleExpression::MODE_HOURLY,
                            '00:00:00'
                        )
                    : '工单仅支持人工关闭',
            ],
            [
                'label' => '未付款订单清理',
                'value' => $config['pending_order_cleanup_enabled'] ? '已开启' : '已关闭',
                'note' => $config['pending_order_cleanup_enabled']
                    ? "未付款订单保留 {$config['pending_order_cleanup_after_hours']} 小时，任务周期："
                        . AutomationScheduleExpression::describe(
                            (string) $config['order_cleanup_schedule_mode'],
                            (string) $config['order_cleanup_schedule_time'],
                            AutomationScheduleExpression::MODE_EVERY_FIVE_MINUTES,
                            '00:00:00'
                        )
                    : '不会自动取消未付款订单',
            ],
        ];
    }
}
