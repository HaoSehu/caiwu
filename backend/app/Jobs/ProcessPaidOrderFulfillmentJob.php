<?php

namespace App\Jobs;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\Finance\InvoicePaidOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaidOrderFulfillmentJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 1200;

    public int $uniqueFor = 1500;

    public array $backoff = [30, 120, 300];

    public function __construct(public int $orderId)
    {
        $this->onQueue('provision');
        $this->afterCommit();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("job:paid-order-fulfillment:{$this->orderId}"))
                ->releaseAfter(10)
                ->expireAfter(1500),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->orderId;
    }

    public function handle(InvoicePaidOrchestrator $paymentService): void
    {
        $paymentService->processPaidOrderFulfillmentById($this->orderId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[支付后自动开通] 队列任务失败', [
            'order_id' => $this->orderId,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);

        // 重试耗尽 = 自动履约已放弃：给已付未履约的新购账单打 requires_refund 标记，
        // 避免用户资金悬挂在无人认领的已付订单上。标记口径与续费链路
        // （ServiceRenewService::flagFailedPaidRenewForRefund 写 config_snapshot['requires_refund']）一致，
        // 管理端账单详情经 AdminInvoiceDetailResource 的 config_snapshot 直接可见。
        try {
            $this->markFulfillmentRequiresRefund();
        } catch (\Throwable $markException) {
            Log::error('[支付后自动开通] requires_refund 标记写入失败', [
                'order_id' => $this->orderId,
                'message' => $markException->getMessage(),
                'exception' => $markException::class,
            ]);
        }
    }

    /**
     * 给已付未履约的新购账单打退款标记：只标记，不自动退款——开通失败可能需要
     * 人工先与上游核实。守卫对齐 TuraIDC markOrderFulfillmentRequiresRefund 语义，
     * 按 caiwu 现状适配：订单状态无「完成」态，已取消/已退款订单不存在资金悬挂。
     */
    private function markFulfillmentRequiresRefund(): void
    {
        $order = Order::query()->find($this->orderId);

        // 本 Job 对 new/renew/upgrade 订单都会派发；续费与升级有各自的恢复与退款链路，
        // 只有新购订单在此打标。
        if (! $order instanceof Order || (string) $order->type !== 'new') {
            return;
        }

        if (in_array((int) $order->status, [OrderStatus::CANCELLED, OrderStatus::REFUNDED], true)) {
            return;
        }

        $invoice = $order->invoice;

        if (! $invoice instanceof Invoice || (int) $invoice->status !== InvoiceStatus::PAID) {
            return;
        }

        $configSnapshot = is_array($invoice->config_snapshot ?? null) ? $invoice->config_snapshot : [];

        // 幂等：已标记的账单不重复写
        if (! empty($configSnapshot['requires_refund'])) {
            return;
        }

        $invoice->forceFill([
            'config_snapshot' => array_merge($configSnapshot, ['requires_refund' => true]),
        ])->save();

        Log::error('[支付后自动开通] 履约重试耗尽，已付账单标记 requires_refund 待人工处理', [
            'order_id' => $this->orderId,
            'invoice_id' => (int) $invoice->id,
            'invoice_no' => (string) ($invoice->invoice_no ?? ''),
            'paid_amount' => (string) ($invoice->paid_amount ?? ''),
        ]);
    }
}
