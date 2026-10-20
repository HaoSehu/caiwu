<?php

namespace App\Jobs;

use App\Services\System\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 通用模板邮件队列任务：把请求路径内的 SMTP 发送改为异步。
 * 载荷在派发侧已完成数据组装，任务内只做模板渲染与发送，失败由队列重试兜底。
 *
 * deliveryKey 在任务构造时生成，随队列载荷序列化；同一任务的重试沿用同一个键，
 * 使 service 层能把多次尝试归并到同一条 message_logs 并逐次落 attempts。
 */
class SendTemplateEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120, 300];

    /** 跨重试稳定的投递键（任务构造时生成，序列化后保持不变） */
    public string $deliveryKey;

    public function __construct(
        public string $email,
        public string $templateCode,
        public array $payload = [],
    ) {
        $this->deliveryKey = (string) Str::uuid();
        $this->onQueue('notification');
        $this->afterCommit();
    }

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->sendTemplateEmail(
            $this->email,
            $this->templateCode,
            $this->payload,
            $this->deliveryKey,
            max(1, $this->attempts()),
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[模板邮件] 队列任务失败', [
            'email' => $this->email,
            'template_code' => $this->templateCode,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
