<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Services\Automation\AutoRenewService;
use App\Services\Automation\BillingAutomationService;
use App\Services\Automation\InvoiceCleanupAutomationService;
use App\Services\Automation\ServiceLifecycleAutomationService;
use App\Services\Automation\ServiceStatusSyncService;
use App\Services\Finance\CouponCampaignService;
use App\Services\ProductCatalog\ProductCatalogService;
use App\Services\Referral\ReferralService;
use App\Services\Ticket\TicketAutomationService;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class RunScheduleTaskJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 900;

    public int $backoff = 60;

    public function __construct(
        public string $taskKey,
        public ?int $adminUserId = null,
    ) {
        $this->onQueue('default');
        $this->afterCommit();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("job:schedule-task:{$this->taskKey}"))
                ->releaseAfter(30)
                ->expireAfter(1200),
        ];
    }

    public function handle(
        AutoRenewService $autoRenewService,
        BillingAutomationService $billingAutomationService,
        CouponCampaignService $couponCampaignService,
        InvoiceCleanupAutomationService $invoiceCleanupAutomationService,
        ProductCatalogService $productCatalogService,
        ProviderResolver $providerResolver,
        ReferralService $referralService,
        ServiceLifecycleAutomationService $serviceLifecycleAutomationService,
        ServiceStatusSyncService $serviceStatusSyncService,
        TicketAutomationService $ticketAutomationService,
    ): void {
        Log::info('[手动任务触发] 开始执行', [
            'task' => $this->taskKey,
            'admin_user_id' => $this->adminUserId,
        ]);

        $result = match ($this->taskKey) {
            'refresh-hosting-panel-auth' => $this->refreshHostingPanelAuth($providerResolver),
            'service-auto-renew' => $autoRenewService->handle(10),
            'referral-release-rewards' => [
                'released' => $referralService->releaseMaturedRewards(),
            ],
            'billing-maintenance' => $billingAutomationService->handle(),
            'coupon-campaign-dispatch' => $couponCampaignService->dispatchDueCampaigns(),
            'product-upstream-config-sync' => $productCatalogService->syncUpstreamProductConfigOptions(),
            'service-lifecycle-maintenance' => $serviceLifecycleAutomationService->handle(),
            'service-status-sync' => $serviceStatusSyncService->handle(),
            'ticket-auto-close' => $ticketAutomationService->handle(),
            'invoice-cleanup' => $invoiceCleanupAutomationService->handle(),
            'order-cleanup' => $invoiceCleanupAutomationService->handle(),
            'sync-processing-order-status' => $this->syncProcessingOrderStatus(),
            'queue-backlog-drain' => $this->drainQueueBacklog(),
            default => throw new InvalidArgumentException('不支持的任务：'.$this->taskKey),
        };

        Log::info('[手动任务触发] 执行完成', [
            'task' => $this->taskKey,
            'admin_user_id' => $this->adminUserId,
            'result' => $result,
        ]);
    }

    private function refreshHostingPanelAuth(ProviderResolver $providerResolver): array
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
            ->each(function (Supplier $supplier) use ($providerResolver, &$summary): void {
                $provider = $providerResolver->resolveForSupplier($supplier);
                if (! $provider->supports(ProvidesScheduledAuthRefresh::class)) {
                    return;
                }

                $summary['matched']++;

                try {
                    $provider->require(ProvidesScheduledAuthRefresh::class, '当前供应商不支持认证刷新')
                        ->refreshJwt($supplier);
                    $summary['refreshed']++;
                } catch (\Throwable $exception) {
                    $summary['failed']++;

                    Log::error('[定时任务] JWT 刷新失败', [
                        'supplier_id' => $supplier->id,
                        'error' => $exception->getMessage(),
                        'exception' => $exception::class,
                    ]);
                }
            });

        Log::info('[定时任务] 接口认证刷新执行完成', $summary);

        return $summary;
    }

    private function syncProcessingOrderStatus(): array
    {
        $exitCode = Artisan::call('orders:sync-processing-status');

        return [
            'exit_code' => $exitCode,
        ];
    }

    private function drainQueueBacklog(): array
    {
        $exitCode = Artisan::call('queue:work', [
            '--queue' => (string) config('queue.caiwu_worker_queues', 'provision,referral,notification,coupon,default'),
            '--sleep' => 1,
            '--tries' => (int) config('queue.caiwu_worker_tries', 3),
            '--timeout' => (int) config('queue.caiwu_worker_timeout', 1200),
            '--stop-when-empty' => true,
            '--max-time' => 50,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('队列积压消费执行失败，退出码：'.$exitCode);
        }

        return [
            'exit_code' => $exitCode,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[手动任务触发] 执行失败，已交由队列失败机制记录', [
            'task' => $this->taskKey,
            'admin_user_id' => $this->adminUserId,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
