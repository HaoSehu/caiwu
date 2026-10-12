<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\HostingPanel\Lib;

use App\Models\Supplier;
use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\Data\TaskContext;
use App\Services\Automation\Heartbeat\ScheduleRule;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\ProviderKey;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 上游集成任务（hosting_panel 插件注册）：接口认证刷新。
 *
 * 定时刷新系统内置主机面板接口认证会话；通过 config.php 的 scheduled_tasks
 * 声明注册进心跳体系，插件停用后任务自动下线。插件供应商（如 ZJMF 财务）
 * 由各自插件任务负责。
 */
final class HostingPanelAuthRefreshTask implements ScheduledTask
{
    public const KEY = 'refresh-hosting-panel-auth';

    public function __construct(
        private readonly ProviderResolver $providerResolver,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return '接口认证刷新';
    }

    public function description(): string
    {
        return '定时刷新系统内置主机面板接口认证会话；插件供应商由各自插件任务负责。归属主机面板接口插件。';
    }

    public function category(): string
    {
        return '供应商接口';
    }

    public function triggers(): array
    {
        return [ScheduleRule::everyTicks(1)];
    }

    public function handle(TaskContext $context): array
    {
        $summary = [
            'matched' => 0,
            'refreshed' => 0,
            'failed' => 0,
        ];

        Supplier::query()
            ->enabled()
            ->orderBy('id')
            ->get()
            ->each(function (Supplier $supplier) use (&$summary): void {
                $provider = $this->providerResolver->resolveForSupplier($supplier);
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

    public function queue(): string
    {
        return (string) config('queue.caiwu_schedule_queue', 'automation');
    }

    public function timeout(): int
    {
        return 600;
    }

    public function lockTtlSeconds(): int
    {
        return 660;
    }

    public function manualTriggerable(): bool
    {
        return true;
    }
}
