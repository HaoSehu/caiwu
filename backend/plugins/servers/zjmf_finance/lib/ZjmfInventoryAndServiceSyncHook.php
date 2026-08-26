<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Lib;

use App\Services\Automation\Contracts\ScheduleHook;
use App\Services\Automation\ServiceStatusSyncService;
use App\Services\ProductCatalog\ProductCatalogService;
use App\Services\Upstream\ProviderKey;
use Illuminate\Support\Facades\Log;

final class ZjmfInventoryAndServiceSyncHook implements ScheduleHook
{
    public function __construct(
        private readonly ProductCatalogService $productCatalogService,
        private readonly ServiceStatusSyncService $serviceStatusSyncService,
    ) {}

    public function handle(string $hook, array $context = []): array
    {
        // 商品与状态同步分步隔离：任一步失败不阻断另一步，失败在汇总中可观测。
        try {
            $products = $this->productCatalogService->syncUpstreamProductStocks(ProviderKey::ZJMF_FINANCE_API);
            $productResult = ['status' => 'success', 'result' => $products];
        } catch (\Throwable $exception) {
            Log::error('[定时任务] ZJMF 财务商品库存同步失败', [
                'hook' => $hook,
                'source' => $context['source'] ?? null,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            $productResult = ['status' => 'failed', 'error' => $exception->getMessage()];
        }

        try {
            $services = $this->serviceStatusSyncService->handleProvider(ProviderKey::ZJMF_FINANCE_API);
            $serviceResult = ['status' => 'success', 'result' => $services];
        } catch (\Throwable $exception) {
            Log::error('[定时任务] ZJMF 财务服务状态同步失败', [
                'hook' => $hook,
                'source' => $context['source'] ?? null,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            $serviceResult = ['status' => 'failed', 'error' => $exception->getMessage()];
        }

        $summary = [
            'products' => $products ?? [],
            'services' => $services ?? [],
            'steps' => [
                'inventory' => $productResult,
                'services' => $serviceResult,
            ],
        ];

        Log::info('[定时任务] ZJMF 财务库存与服务同步执行完成', array_merge($summary, [
            'hook' => $hook,
            'source' => $context['source'] ?? null,
        ]));

        return $summary;
    }
}
