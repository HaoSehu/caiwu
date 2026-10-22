<?php

namespace App\Jobs;

use App\Services\Finance\InvoicePaidOrchestrator;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
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

    /**
     * 同订单的队列任务是否已在唯一锁中（应由队列而非本次同步兜底执行）。
     *
     * Job 实现 ShouldBeUnique，重复派发会在 PendingDispatch 析构里被静默丢弃、不抛异常，
     * 调用方无法用 try/catch 判断。这里用与框架相同的锁键主动探测一次并立即释放，
     * 让兜底路径能把「已有人在队列里跑」和「真的派发出去了」区分开，不写假日志。
     */
    public static function isUniqueLocked(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        $job = new self($orderId);
        $lock = Cache::lock(UniqueLock::getKey($job), max(1, (int) $job->uniqueFor));

        if (! $lock->get()) {
            return true;
        }

        $lock->release();

        return false;
    }

    public function handle(InvoicePaidOrchestrator $paymentService): void
    {
        $paymentService->processPaidOrderFulfillmentById($this->orderId);
    }

    public function failed(\Throwable $exception): void
    {
        $connection = (string) ($this->job?->getConnectionName() ?? $this->connection ?? '');
        $attempts = max(1, $this->attempts());
        $maxAttempts = max(1, (int) $this->tries);

        Log::error('[支付后自动开通] 队列任务失败', [
            'order_id' => $this->orderId,
            'attempts' => $attempts,
            'max_attempts' => $maxAttempts,
            'connection' => $connection,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);

        // failed() 不等于「重试耗尽」：队列驱动会为每次失败调用它，同步驱动（dispatchSync）
        // 更是第一次失败就调用。此时上游仍会重试或交给 provision:retry-failed 巡检兜底，
        // 直接打 requires_refund 会把「还在自动重试」的账单误标成待人工退款。
        if ($connection === 'sync' || $attempts < $maxAttempts) {
            Log::warning('[支付后自动开通] 本次失败仍在自动重试范围内，暂不打退款标记', [
                'order_id' => $this->orderId,
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
                'connection' => $connection,
            ]);

            return;
        }

        // 重试耗尽 = 自动履约已放弃：给已付未履约的新购账单打 requires_refund 标记。
        // 标记逻辑已提取到 InvoicePaidOrchestrator::markOrderFulfillmentRequiresRefund，
        // 与 provision:retry-failed 超时巡检共用。
        try {
            app(InvoicePaidOrchestrator::class)->markOrderFulfillmentRequiresRefund(
                $this->orderId,
                'queue_failed',
            );
        } catch (\Throwable $markException) {
            Log::error('[支付后自动开通] requires_refund 标记写入失败', [
                'order_id' => $this->orderId,
                'message' => $markException->getMessage(),
                'exception' => $markException::class,
            ]);
        }
    }
}
