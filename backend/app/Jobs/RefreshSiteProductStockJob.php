<?php

namespace App\Jobs;

use App\Services\ProductCatalog\ProductSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 站点商品库存后台刷新任务（stale-while-revalidate 的 revalidate 段）。
 * 前台库存缓存过期时先回陈旧值，由本任务重新拉取上游实时库存写入缓存，
 * 避免过期瞬间的站点请求同步等待上游 HTTP。上游失败时仅记日志，
 * 保留旧值继续对外服务，等下次过期重新触发刷新。
 */
class RefreshSiteProductStockJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(public int $productId)
    {
        $this->onQueue('provision');
    }

    public function handle(ProductSyncService $productSyncService): void
    {
        $productSyncService->refreshSiteProductStock($this->productId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('[站点库存] 后台刷新失败，保留旧值继续服务', [
            'product_id' => $this->productId,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
