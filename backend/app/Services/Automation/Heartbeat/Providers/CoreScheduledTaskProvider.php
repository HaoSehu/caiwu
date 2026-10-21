<?php

declare(strict_types=1);

namespace App\Services\Automation\Heartbeat\Providers;

use App\Services\Automation\AutoRenewService;
use App\Services\Automation\BillingAutomationService;
use App\Services\Automation\Heartbeat\CallbackScheduledTask;
use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\Contracts\ScheduledTaskProvider;
use App\Services\Automation\Heartbeat\Contracts\TriggerRule;
use App\Services\Automation\Heartbeat\Data\TaskContext;
use App\Services\Automation\Heartbeat\ScheduleRule;
use App\Services\Automation\InvoiceCleanupAutomationService;
use App\Services\Automation\ServiceLifecycleAutomationService;
use App\Services\Referral\ReferralService;
use App\Services\System\SettingService;
use App\Services\Ticket\TicketAutomationService;
use App\Support\AutomationScheduleExpression;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CoreScheduledTaskProvider implements ScheduledTaskProvider
{
    public function __construct(
        private SettingService $settingService,
    ) {}

    public function tasks(): array
    {
        $automation = $this->automationConfig();

        return [
            $this->task(
                key: 'service-auto-renew',
                title: '服务自动续费',
                category: '服务续费',
                description: '扫描开启自动续费的服务，余额充足时自动创建续费账单并完成支付处理。',
                triggers: [ScheduleRule::everyTicks(4)],
                handler: function (): array {
                    $summary = app(AutoRenewService::class)->handle();
                    if (($summary['matched'] ?? 0) > 0) {
                        Log::debug('[定时任务] 自动续费执行完成', $summary);
                    } else {
                        Log::debug('[定时任务] 自动续费执行完成（无匹配）', $summary);
                    }

                    return $summary;
                },
                timeout: 900,
                lockTtlSeconds: 960,
            ),
            $this->task(
                key: 'referral-release-rewards',
                title: '推荐奖励释放',
                category: '推荐奖励',
                description: '把已过冻结期的推荐奖励转入可提现余额，并记录推荐账户流水。',
                triggers: [ScheduleRule::everyTicks(1)],
                handler: function (): array {
                    $released = app(ReferralService::class)->releaseMaturedRewards();
                    if ($released > 0) {
                        Log::debug('[定时任务] 推荐奖励释放执行完成', ['released' => $released]);
                    }

                    return ['released' => $released];
                },
                timeout: 600,
                lockTtlSeconds: 1200,
            ),
            $this->task(
                key: 'service-lifecycle-maintenance',
                title: '服务生命周期维护',
                category: '服务生命周期',
                description: '处理服务到期暂停、暂停通知和到期后自动取消。',
                triggers: [ScheduleRule::automation(
                    (string) ($automation['service_lifecycle_schedule_mode'] ?? AutomationScheduleExpression::MODE_EVERY_FIFTEEN_MINUTES),
                    (string) ($automation['service_lifecycle_schedule_time'] ?? '00:00:00'),
                    AutomationScheduleExpression::MODE_EVERY_FIFTEEN_MINUTES,
                    '00:00:00',
                )],
                handler: function (): array {
                    $summary = app(ServiceLifecycleAutomationService::class)->handle();
                    if (array_sum($summary) > 0) {
                        Log::debug('[定时任务] 服务生命周期维护执行完成', $summary);
                    }

                    return $summary;
                },
                timeout: 900,
                lockTtlSeconds: 960,
            ),
            $this->task(
                key: 'billing-maintenance',
                title: '账单自动化维护',
                category: '账单提醒',
                description: '处理续费提醒、自动生成账单、账单到期提醒与逾期催付。',
                triggers: [ScheduleRule::automation(
                    (string) ($automation['billing_maintenance_schedule_mode'] ?? AutomationScheduleExpression::MODE_HOURLY),
                    (string) ($automation['billing_maintenance_schedule_time'] ?? '00:00:00'),
                    AutomationScheduleExpression::MODE_HOURLY,
                    '00:00:00',
                )],
                handler: function (): array {
                    $summary = app(BillingAutomationService::class)->handle();
                    Log::debug('[定时任务] 账单自动化维护执行完成', $summary);

                    return $summary;
                },
                timeout: 1200,
                lockTtlSeconds: 1800,
            ),
            $this->task(
                key: 'log-archive',
                title: '日志归档',
                category: '系统维护',
                description: '每天归档超过保留期限的普通日志，并写入归档审计与执行报告。协议由 log_archive.protocol 控制。',
                triggers: [ScheduleRule::cron('0 2 * * *')],
                handler: function (): array {
                    $protocol = strtolower(trim((string) config('log_archive.protocol', 'v1')));

                    return match ($protocol) {
                        'v1' => $this->runArtisan('db:archive-logs', [
                            '--execute' => true,
                            '--json' => true,
                        ]),
                        'v2' => $this->runArtisan('db:archive-logs-v2', [
                            '--execute' => true,
                            '--purge' => true,
                            '--json' => true,
                        ]),
                        default => throw new RuntimeException("不支持的日志归档协议 [{$protocol}]，已拒绝执行归档。"),
                    };
                },
                timeout: 3600,
                lockTtlSeconds: 3660,
                manualTriggerable: false,
            ),
            // 高增长日志表容量护栏（P1-14 替代方案）：超阈值时以非零退出码告警，
            // 届时再评估分区或缩短在线窗口。只读，每 6 小时检查一次。
            $this->task(
                key: 'log-capacity-guard',
                title: '日志表容量护栏',
                category: '系统维护',
                description: '只读检查 10 张高增长日志/审计表容量，任一张超过阈值（默认 2GB）时告警，提示评估分区或缩短归档窗口。',
                triggers: [ScheduleRule::everyTicks(24)],
                handler: fn (): array => $this->runArtisan('db:log-capacity-guard', [
                    '--json' => true,
                ]),
                timeout: 120,
                lockTtlSeconds: 180,
                manualTriggerable: true,
            ),
            $this->task(
                key: 'log-archive-health',
                title: '日志归档健康与容量检查',
                category: '系统维护',
                description: '每小时只读检查日志归档候选积压、最近成功/失败批次与归档文件规模，并采集数据库日志表容量与磁盘空间快照供趋势观测。',
                triggers: [ScheduleRule::everyTicks(4)],
                handler: fn (): array => $this->runHealthCheck(),
                timeout: 300,
                lockTtlSeconds: 360,
                manualTriggerable: true,
            ),
            // 混合支付「预下单失败且预扣余额回收失败」的兜底重放（CMP-P1-2）。
            // 每 5 分钟一次：待回补金额通常很小但停在用户余额上，拖久了会变成投诉。
            $this->task(
                key: 'mix-restore-replay',
                title: '混合支付待回补重放',
                category: '财务',
                description: '重放「预下单失败但预扣余额回收失败」的记录（finance_reconciliation_diffs 中 mix_balance_pending_restore 的 open 行），把滞留在账上的预扣余额退回用户。',
                triggers: [ScheduleRule::everyTicks(1)],
                handler: fn (): array => $this->runArtisan('finance:replay-mix-restore', [
                    '--limit' => 100,
                    '--json' => true,
                ]),
                timeout: 300,
                lockTtlSeconds: 360,
                manualTriggerable: true,
            ),
            // 财务账务一致性审计（只读）：余额 vs 流水合计、来源完整性、充值桥接覆盖。
            // strict 只对金额级差异退出非零；source_type/trace 存量缺口只作为报告项。
            $this->task(
                key: 'finance-ledger-audit',
                title: '财务流水一致性审计',
                category: '财务',
                description: '每日只读审计财务台账：余额与流水合计是否一致、来源字段完整性、充值账单桥接覆盖；只对金额级差异告警。',
                triggers: [ScheduleRule::cron('20 4 * * *')],
                handler: fn (): array => $this->runArtisan('finance:audit-ledger-consistency', [
                    '--json' => true,
                ]),
                timeout: 600,
                lockTtlSeconds: 900,
                manualTriggerable: true,
            ),
            // 财务幂等记录清理：按 config finance.idempotency.ttl_days 保留期删除旧行。
            $this->task(
                key: 'finance-operations-prune',
                title: '财务幂等记录清理',
                category: '财务',
                description: '每天清理超过保留期（默认 30 天）的财务幂等记录，防止 finance_operations 无限增长。',
                triggers: [ScheduleRule::cron('40 4 * * *')],
                handler: fn (): array => $this->runArtisan('finance:prune-operations', [
                    '--execute' => true,
                ]),
                timeout: 300,
                lockTtlSeconds: 420,
                manualTriggerable: true,
            ),
            $this->task(
                key: 'stale-record-prune',
                title: '过期记录清理',
                category: '系统维护',
                description: '每天清理过期即无价值的记录：过期/闲置 API 令牌、密码重置令牌与超期失败任务（保留 30 天）。直接删除不归档，与 02:00 日志归档错峰。',
                triggers: [ScheduleRule::cron('30 3 * * *')],
                handler: fn (): array => $this->runArtisan('db:prune-stale-records', [
                    '--execute' => true,
                    '--json' => true,
                ]),
                timeout: 600,
                lockTtlSeconds: 900,
                manualTriggerable: true,
            ),
            $this->task(
                key: 'ticket-auto-close',
                title: '工单自动关闭',
                category: '工单管理',
                description: '关闭超过阈值且长期无客户回复的工单。',
                triggers: [ScheduleRule::automation(
                    (string) ($automation['ticket_auto_close_schedule_mode'] ?? AutomationScheduleExpression::MODE_HOURLY),
                    (string) ($automation['ticket_auto_close_schedule_time'] ?? '00:00:00'),
                    AutomationScheduleExpression::MODE_HOURLY,
                    '00:00:00',
                )],
                handler: function (): array {
                    $summary = app(TicketAutomationService::class)->handle();
                    Log::debug('[定时任务] 工单自动关闭执行完成', $summary);

                    return $summary;
                },
                timeout: 900,
                lockTtlSeconds: 1200,
            ),
            $this->task(
                key: 'order-cleanup',
                title: '账单与充值清理',
                category: '账单清理',
                description: '自动取消超过 5 分钟未付款的订单、账单和充值单。',
                triggers: [ScheduleRule::automation(
                    (string) ($automation['order_cleanup_schedule_mode'] ?? AutomationScheduleExpression::MODE_EVERY_FIFTEEN_MINUTES),
                    (string) ($automation['order_cleanup_schedule_time'] ?? '00:00:00'),
                    AutomationScheduleExpression::MODE_EVERY_FIFTEEN_MINUTES,
                    '00:00:00',
                )],
                handler: function (): array {
                    $summary = app(InvoiceCleanupAutomationService::class)->handle();
                    if (array_sum($summary) > 0) {
                        Log::debug('[定时任务] 账单与充值清理执行完成', $summary);
                    }

                    return $summary;
                },
                timeout: 900,
                lockTtlSeconds: 960,
            ),
            $this->task(
                key: 'site-cache-warmup',
                title: '首页缓存预热',
                category: '站点缓存',
                description: '刷新首页与产品目录缓存，保持站点缓存热度。',
                triggers: [ScheduleRule::everyTicks(1)],
                handler: fn (): array => $this->runArtisan('app:warmup-site-cache'),
                timeout: 900,
                lockTtlSeconds: 960,
            ),
            $this->task(
                key: 'compensate-recharge-invoices',
                title: '充值账单补偿',
                category: '财务补偿',
                description: '扫描成功但缺失账单关联的充值支付记录，补建充值账单。',
                triggers: [ScheduleRule::everyTicks(4)],
                handler: fn (): array => $this->runArtisan('payment:compensate-recharge-invoices', ['--limit' => 200]),
                timeout: 1200,
                lockTtlSeconds: 1800,
            ),
        ];
    }

    /**
     * @param  list<TriggerRule>  $triggers
     * @param  Closure(TaskContext):mixed  $handler
     */
    private function task(
        string $key,
        string $title,
        string $category,
        string $description,
        array $triggers,
        Closure $handler,
        ?string $queue = null,
        int $timeout = 900,
        int $lockTtlSeconds = 1200,
        bool $manualTriggerable = true,
    ): ScheduledTask {
        return new CallbackScheduledTask(
            key: $key,
            title: $title,
            description: $description,
            category: $category,
            triggers: $triggers,
            handler: $handler,
            queue: $queue,
            timeout: $timeout,
            lockTtlSeconds: $lockTtlSeconds,
            manualTriggerable: $manualTriggerable,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function automationConfig(): array
    {
        try {
            config([
                'idc.schedule_runtime.automation_config' => [
                    'status' => 'loaded',
                    'fallback_reason' => '',
                ],
            ]);

            return $this->settingService->getAutomationConfig();
        } catch (Throwable $exception) {
            config([
                'idc.schedule_runtime.automation_config' => [
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
    }

    /**
     * 容量命令的 JSON 包含完整表明细，不能复用 runArtisan 的通用输出截断；
     * 这里只保留趋势所需的紧凑字段，避免运行台账被大表明细撑大。
     *
     * @return array<string, mixed>
     */
    private function runCapacitySnapshot(): array
    {
        $command = 'db:logs:capacity';
        $parameters = ['--json' => true];
        $exitCode = Artisan::call($command, $parameters);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            $message = "Artisan command [{$command}] exited with code {$exitCode}.";
            if ($output !== '') {
                $message .= ' Output: '.mb_substr($output, 0, 1000);
            }

            throw new RuntimeException($message);
        }

        try {
            $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('日志容量命令返回了无效 JSON。', 0, $exception);
        }

        if (! is_array($payload)) {
            throw new RuntimeException('日志容量命令返回了无效 JSON 结构。');
        }

        foreach (['summary', 'storage_logs', 'log_archives', 'disk'] as $key) {
            if (! array_key_exists($key, $payload)) {
                throw new RuntimeException("日志容量命令响应缺少 {$key} 字段。");
            }
        }

        return [
            'command' => $command,
            'exit_code' => $exitCode,
            'parameters' => $parameters,
            'generated_at' => $payload['generated_at'] ?? null,
            'database' => $payload['database'] ?? null,
            'summary' => $payload['summary'],
            'storage_logs' => $payload['storage_logs'],
            'log_archives' => $payload['log_archives'],
            'disk' => $payload['disk'],
        ];
    }

    /**
     * 健康命令的 warning/unknown 状态即使 CLI 保持兼容退出码 0，也必须让心跳任务失败，
     * 否则任务台账会把异常健康状态记为成功，外部监控无法感知。
     *
     * 容量快照（原独立任务 log-capacity-snapshot）合并为本任务的附加观测维度：
     * 只读采集失败不改变健康判定，仅以 capacity_error 字段记录原因。
     *
     * @return array<string, mixed>
     */
    private function runHealthCheck(): array
    {
        $command = 'db:archive-logs:health';
        $parameters = ['--json' => true];
        $exitCode = Artisan::call($command, $parameters);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            $message = "Artisan command [{$command}] exited with code {$exitCode}.";
            if ($output !== '') {
                $message .= ' Output: '.mb_substr($output, 0, 1000);
            }

            throw new RuntimeException($message);
        }

        try {
            $payload = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('日志归档健康命令返回了无效 JSON。', 0, $exception);
        }

        if (! is_array($payload)) {
            throw new RuntimeException('日志归档健康命令返回了无效 JSON 结构。');
        }

        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        if (! in_array($status, ['healthy', 'warning', 'unknown', 'critical'], true)) {
            throw new RuntimeException('日志归档健康命令响应缺少有效 status。');
        }
        if ($status !== 'healthy') {
            throw new RuntimeException("日志归档健康状态为 {$status}，已记录为任务失败。");
        }

        $summary = [
            'command' => $command,
            'exit_code' => $exitCode,
            'parameters' => $parameters,
            'status' => $status,
            'generated_at' => $payload['generated_at'] ?? null,
            'protocol' => $payload['protocol'] ?? null,
            'total_eligible_rows' => (int) ($payload['total_eligible_rows'] ?? 0),
            'unavailable_tables' => $payload['unavailable_tables'] ?? [],
            'audit' => $payload['audit'] ?? null,
            'v2' => $payload['v2'] ?? null,
        ];

        try {
            $summary['capacity'] = $this->runCapacitySnapshot();
        } catch (Throwable $exception) {
            $summary['capacity_error'] = $exception->getMessage();
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function runArtisan(string $command, array $parameters = [], bool $throwOnFailure = true): array
    {
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

        if ($exitCode !== 0 && $throwOnFailure) {
            $message = "Artisan command [{$command}] exited with code {$exitCode}.";
            if ($output !== '') {
                $message .= ' Output: '.mb_substr($output, 0, 1000);
            }

            throw new RuntimeException($message);
        }

        return $summary;
    }
}
