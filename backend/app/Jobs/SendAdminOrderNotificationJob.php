<?php

namespace App\Jobs;

use App\Services\Finance\AdminOrderNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 管理员订单事件通知（created/paid）队列任务。
 * 取代 terminating 回调内串行 SMTP 发信：邮件多或 SMTP 慢时不再占用 FPM worker，
 * 失败由队列重试兜底；幂等由 AdminOrderNotificationService 的 AutomationLog 规则键保证。
 */
class SendAdminOrderNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [60, 300, 600];

    public function __construct(
        public int $orderId,
        public string $event,
    ) {
        $this->onQueue('notification');
        $this->afterCommit();
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("job:admin-order-notify:{$this->orderId}:{$this->event}"))
                ->releaseAfter(10)
                ->expireAfter(600),
        ];
    }

    public function handle(AdminOrderNotificationService $service): void
    {
        $service->notifyOrderEventNow($this->orderId, $this->event);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[管理员订单通知] 队列任务失败', [
            'order_id' => $this->orderId,
            'event' => $this->event,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
