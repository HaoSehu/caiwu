<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\MofangFinance\Lib;

use App\Services\Automation\Contracts\ScheduleHook;
use App\Services\Automation\ServiceStatusSyncService;
use App\Services\ProductCatalog\ProductCatalogService;
use App\Services\Upstream\ProviderKey;
use Illuminate\Support\Facades\Log;

final class MofangInventoryAndServiceSyncHook implements ScheduleHook
{
    public function __construct(
        private readonly ProductCatalogService $productCatalogService,
        private readonly ServiceStatusSyncService $serviceStatusSyncService,
    ) {}

    public function handle(string $hook, array $context = []): array
    {
        $products = $this->productCatalogService->syncUpstreamProductStocks(ProviderKey::MOFANG_FINANCE_API);
        $services = $this->serviceStatusSyncService->handleProvider(ProviderKey::MOFANG_FINANCE_API);

        $summary = [
            'products' => $products,
            'services' => $services,
        ];

        Log::info('[定时任务] 魔方财务库存与服务同步执行完成', array_merge($summary, [
            'hook' => $hook,
            'source' => $context['source'] ?? null,
        ]));

        return $summary;
    }
}
