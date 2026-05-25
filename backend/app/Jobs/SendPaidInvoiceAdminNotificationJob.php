<?php

namespace App\Jobs;

use App\Services\Finance\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class SendPaidInvoiceAdminNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 600];

    public function __construct(public int $invoiceId)
    {
        $this->onQueue('notification');
        $this->afterCommit();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("job:paid-invoice-notify:{$this->invoiceId}"))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    public function handle(PaymentService $paymentService): void
    {
        $paymentService->processPaidInvoiceAdminNotifyById($this->invoiceId);
    }
}
