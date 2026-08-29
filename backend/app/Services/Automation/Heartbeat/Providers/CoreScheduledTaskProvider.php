<?php

declare(strict_types=1);

namespace App\Services\Automation\Heartbeat\Providers;

use App\Models\Supplier;
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
use App\Services\Automation\ServiceStatusSyncService;
use App\Services\Finance\CouponCampaignService;
use App\Services\ProductCatalog\ProductCatalogService;
use App\Services\Referral\ReferralService;
use App\Services\System\SettingService;
use App\Services\Ticket\TicketAutomationService;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\Contracts\ProvidesSelfStatusSync;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
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
                key: 'refresh-hosting-panel-auth',
                title: '接口认证刷新',
                category: '供应商接口',
                description: '定时刷新系统内置主机面板接口认证会话；插件供应商由各自插件任务负责。',
                triggers: [ScheduleRule::everyTicks(1)],
                handler: fn (): array => $this->refreshHostingPanelAuth(),
                timeout: 600,
                lockTtlSeconds: 660,
            ),
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
                key: 'service-status-sync',
                title: '用户产品状态同步',
                category: '服务状态',
                description: '定时拉取上游实例详情与运行状态，并同步回本地用户服务状态。',
                triggers: [ScheduleRule::everyTicks(1)],
                handler: function (): array {
                    // 启用中且声明 ProvidesSelfStatusSync 能力的上游插件已自带定向状态同步，
                    // 全量任务据此排除对应 provider_key，避免同一服务被上游重复拉取；
                    // 插件停用后自动回退全量兜底。
                    $summary = app(ServiceStatusSyncService::class)->handle(100, 10, $this->selfSyncedProviderKeys());
                    Log::debug('[定时任务] 用户产品状态同步执行完成', $summary);

                    return $summary;
                },
                timeout: 1200,
                lockTtlSeconds: 1800,
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
                key: 'product-upstream-config-sync',
                title: '上游产品配置同步',
                category: '商品同步',
                description: '每 24 小时拉取已绑定上游商品的配置项，并自动保存到本地商品配置，不同步商品定价。',
                triggers: [ScheduleRule::cron('0 0 * * *')],
                handler: function (): array {
                    $summary = app(ProductCatalogService::class)->syncUpstreamProductConfigOptions();
                    Log::debug('[定时任务] 上游产品配置同步执行完成', $summary);

                    return $summary;
                },
                timeout: 3600,
                lockTtlSeconds: 3660,
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
            $this->task(
                key: 'log-archive-health',
                title: '日志归档健康检查',
                category: '系统维护',
                description: '每小时只读检查日志归档候选积压、最近成功/失败批次与归档文件规模。',
                triggers: [ScheduleRule::everyTicks(4)],
                handler: fn (): array => $this->runHealthCheck(),
                timeout: 300,
                lockTtlSeconds: 360,
                manualTriggerable: true,
            ),
            $this->task(
                key: 'log-capacity-snapshot',
                title: '日志容量快照',
                category: '系统维护',
                description: '每天只读采集数据库日志表容量与日志目录体积，写入运行台账供趋势观测。',
                triggers: [ScheduleRule::cron('0 3 * * *')],
                handler: fn (): array => $this->runCapacitySnapshot(),
                timeout: 300,
                lockTtlSeconds: 360,
                manualTriggerable: true,
            ),
            $this->task(
                key: 'coupon-campaign-dispatch',
                title: '优惠券活动发放',
                category: '营销活动',
                description: '按活动配置的星期与时间自动生成一批公开优惠券，例如每周五 18:00 发放周五特惠。',
                triggers: [ScheduleRule::everyTicks(1)],
                handler: function (): array {
                    $summary = app(CouponCampaignService::class)->dispatchDueCampaigns();
                    if (($summary['triggered'] ?? 0) > 0 || ($summary['failed'] ?? 0) > 0) {
                        Log::debug('[定时任务] 优惠券活动自动发放执行完成', $summary);
                    }

                    return $summary;
                },
                timeout: 900,
                lockTtlSeconds: 960,
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
                key: 'reconcile-account-balance',
                title: '账户余额在线对账',
                category: '财务对账',
                description: '定时执行账户余额在线对账，及时发现余额投影异常。',
                triggers: [ScheduleRule::everyTicks(4)],
                handler: function (): array {
                    $summary = $this->runArtisan('reconcile:account-balance', [], false);
                    if (($summary['exit_code'] ?? 0) !== 0) {
                        Log::warning('[定时任务] 账户余额对账发现差异，已记录告警且不进行队列重试', $summary);
                        $summary['status'] = 'warning';
                    }

                    return $summary;
                },
                timeout: 1200,
                lockTtlSeconds: 1800,
            ),
            $this->task(
                key: 'audit-ledger-consistency',
                title: '资金流水一致性审计',
                category: '财务对账',
                description: '每日审计账户流水来源完整性、余额与流水一致性、充值桥接覆盖，账实不符即告警。',
                triggers: [ScheduleRule::cron('0 3 * * *')],
                handler: function (): array {
                    $summary = $this->runArtisan('finance:audit-ledger-consistency', ['--strict' => true], false);
                    if (($summary['exit_code'] ?? 0) !== 0) {
                        Log::warning('[定时任务] 资金流水一致性审计发现差异，已记录告警且不进行队列重试', $summary);
                        $summary['status'] = 'warning';
                    }

                    return $summary;
                },
                timeout: 1200,
                lockTtlSeconds: 1800,
                manualTriggerable: false,
            ),
            $this->task(
                key: 'provision-retry-failed',
                title: '上游开通孤儿单补偿告警',
                category: '上游开通',
                description: '默认 dry-run 扫描上游开通失败孤儿单并写日志告警。',
                triggers: [ScheduleRule::everyTicks(1)],
                handler: fn (): array => $this->runArtisan('provision:retry-failed'),
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
     * 已启用的上游插件中以 ProvidesSelfStatusSync 能力声明"自带定向状态同步"的 provider_key 集合。
     *
     * 能力判定走 ProviderRegistry 的实时插件清单，不读 capabilities_json 落库快照：
     * manifest 增补能力后部署即生效；插件清单损坏或文件缺失时驱动不注册进排除集，
     * 对应 provider 自动回退全量同步兜底，不会出现"定向任务与全量任务同时失效"。
     *
     * @return list<string>
     */
    private function selfSyncedProviderKeys(): array
    {
        $keys = [];
        foreach (app(ProviderRegistry::class)->all() as $key => $driver) {
            $normalizedKey = trim($key);
            if ($normalizedKey !== '' && $driver->supports(ProvidesSelfStatusSync::class)) {
                $keys[] = $normalizedKey;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function refreshHostingPanelAuth(): array
    {
        $providerResolver = app(ProviderResolver::class);
        $summary = [
            'matched' => 0,
            'refreshed' => 0,
            'failed' => 0,
        ];

        Supplier::query()
            ->enabled()
            ->orderBy('id')
            ->get()
            ->each(function (Supplier $supplier) use ($providerResolver, &$summary): void {
                $provider = $providerResolver->resolveForSupplier($supplier);
                $providerKey = trim((string) ($provider->key() ?? $provider->rawKey() ?? ''));
                if ($providerKey !== ProviderKey::HOSTING_PANEL_API) {
                    return;
                }

                if (! $provider->supports(ProvidesScheduledAuthRefresh::class)) {
                    return;
                }

                $summary['matched']++;

                try {
                    $provider->require(ProvidesScheduledAuthRefresh::class, '当前供应商不支持认证刷新')
                        ->refreshJwt($supplier);
                    $summary['refreshed']++;
                } catch (Throwable $exception) {
                    $summary['failed']++;

                    Log::error('[定时任务] JWT刷新失败', [
                        'supplier_id' => $supplier->id,
                        'error' => $exception->getMessage(),
                        'exception' => $exception::class,
                    ]);
                }
            });

        Log::debug('[定时任务] 接口认证刷新执行完成', $summary);

        return $summary;
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

        return [
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
