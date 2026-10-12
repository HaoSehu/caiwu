<?php

declare(strict_types=1);

namespace App\Services\Upstream\Integration;

use App\Services\Automation\Heartbeat\Contracts\ScheduledTask;
use App\Services\Automation\Heartbeat\Data\TaskContext;
use App\Services\Automation\Heartbeat\ScheduleRule;
use App\Services\ProductCatalog\ProductCatalogService;
use Illuminate\Support\Facades\Log;

/**
 * 上游集成任务：上游产品配置同步。
 *
 * 从系统核心任务清单（CoreScheduledTaskProvider）迁入上游集成模块，
 * 每 24 小时拉取已绑定上游商品的配置项并保存到本地商品配置，不同步商品定价。
 */
final class ProductUpstreamConfigSyncTask implements ScheduledTask
{
    public const KEY = 'product-upstream-config-sync';

    public function __construct(
        private readonly ProductCatalogService $catalogService,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return '上游产品配置同步';
    }

    public function description(): string
    {
        return '每 24 小时拉取已绑定上游商品的配置项，并自动保存到本地商品配置，不同步商品定价。归属上游集成模块。';
    }

    public function category(): string
    {
        return '商品同步';
    }

    public function triggers(): array
    {
        return [ScheduleRule::cron('0 0 * * *')];
    }

    public function handle(TaskContext $context): array
    {
        $summary = $this->catalogService->syncUpstreamProductConfigOptions();
        Log::debug('[定时任务] 上游产品配置同步执行完成', $summary);

        return $summary;
    }

    public function queue(): string
    {
        return (string) config('queue.caiwu_schedule_queue', 'automation');
    }

    public function timeout(): int
    {
        return 3600;
    }

    public function lockTtlSeconds(): int
    {
        return 3660;
    }

    public function manualTriggerable(): bool
    {
        return true;
    }
}
