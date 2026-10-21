<?php

namespace App\Jobs;

use App\Services\Finance\InvoicePaidOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaidOrderReferralRewardJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public array $backoff = [30, 120, 300];

    /**
     * @param  int  $orderId  订单来源的返佣；账单来源（无订单）传 0
     * @param  int|null  $invoiceId  账单来源的返佣（无订单账单）；订单来源传 null
     */
    public function __construct(
        public int $orderId,
        public ?string $traceId = null,
        public ?int $invoiceId = null,
    ) {
        $this->onQueue('referral');
        $this->afterCommit();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('job:paid-referral:'.$this->sourceKey()))
                ->releaseAfter(10)
                ->expireAfter(600),
        ];
    }

    public function uniqueId(): string
    {
        return $this->sourceKey();
    }

    public function handle(InvoicePaidOrchestrator $paymentService): void
    {
        if ($this->invoiceId !== null && $this->invoiceId > 0) {
            $paymentService->processPaidInvoiceReferralRewardById($this->invoiceId, $this->traceId);

            return;
        }

        $paymentService->processPaidOrderReferralRewardById($this->orderId, $this->traceId);
    }

    private function sourceKey(): string
    {
        return $this->invoiceId !== null && $this->invoiceId > 0
            ? 'invoice:'.$this->invoiceId
            : 'order:'.$this->orderId;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[支付后推荐奖励] 队列任务失败', [
            'order_id' => $this->orderId,
            'invoice_id' => $this->invoiceId,
            'trace_id' => $this->traceId,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
