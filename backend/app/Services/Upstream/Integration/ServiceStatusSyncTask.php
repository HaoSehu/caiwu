<?php

declare(strict_types=1);

namespace App\Services\Upstream\Integration;

use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\Data\TaskContext;
use App\Services\Automation\Heartbeat\ScheduleRule;
use App\Services\Automation\ServiceStatusSyncService;
use App\Services\Upstream\Contracts\ProvidesSelfStatusSync;
use App\Services\Upstream\ProviderRegistry;
use Illuminate\Support\Facades\Log;

/**
 * 上游集成任务：用户产品状态同步。
 *
 * 从系统核心任务清单（CoreScheduledTaskProvider）迁入上游集成模块，
 * 定时拉取上游实例详情与运行状态，并同步回本地用户服务状态。
 */
final class ServiceStatusSyncTask implements ScheduledTask
{
    public const KEY = 'service-status-sync';

    public function __construct(
        private readonly ServiceStatusSyncService $syncService,
        private readonly ProviderRegistry $providerRegistry,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return '用户产品状态同步';
    }

    public function description(): string
    {
        return '定时拉取上游实例详情与运行状态，并同步回本地用户服务状态。归属上游集成模块。';
    }

    public function category(): string
    {
        return '服务状态';
    }

    public function triggers(): array
    {
        return [ScheduleRule::everyTicks(1)];
    }

    public function handle(TaskContext $context): array
    {
        // 启用中且声明 ProvidesSelfStatusSync 能力的上游插件已自带定向状态同步，
        // 全量任务据此排除对应 provider_key，避免同一服务被上游重复拉取；
        // 插件停用后自动回退全量兜底。
        $summary = $this->syncService->handle(100, 10, $this->selfSyncedProviderKeys());
        Log::debug('[定时任务] 用户产品状态同步执行完成', $summary);

        return $summary;
    }

    public function queue(): string
    {
        return (string) config('queue.caiwu_schedule_queue', 'automation');
    }

    public function timeout(): int
    {
        return 1200;
    }

    public function lockTtlSeconds(): int
    {
        return 1800;
    }

    public function manualTriggerable(): bool
    {
        return true;
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
        foreach ($this->providerRegistry->all() as $key => $driver) {
            $normalizedKey = trim($key);
            if ($normalizedKey !== '' && $driver->supports(ProvidesSelfStatusSync::class)) {
                $keys[] = $normalizedKey;
            }
        }

        return $keys;
    }
}
