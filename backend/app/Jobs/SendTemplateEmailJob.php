<?php

namespace App\Jobs;

use App\Services\System\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 通用模板邮件队列任务：把请求路径内的 SMTP 发送改为异步。
 * 载荷在派发侧已完成数据组装，任务内只做模板渲染与发送，失败由队列重试兜底。
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

    public function __construct(
        public string $email,
        public string $templateCode,
        public array $payload = [],
    ) {
        $this->onQueue('notification');
        $this->afterCommit();
    }

    public function handle(NotificationService $notificationService): void
    {
        $notificationService->sendTemplateEmail($this->email, $this->templateCode, $this->payload);
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
