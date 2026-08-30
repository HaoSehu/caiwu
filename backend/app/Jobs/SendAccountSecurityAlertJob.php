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
 * 账号安全事件提醒（密码/手机号/邮箱变更）队列任务。
 * 安全提醒属于通知性质，不应让用户的变更请求同步等待 SMTP 往返；
 * 载荷为已脱敏的基础字段，失败由队列重试兜底。
 */
class SendAccountSecurityAlertJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const ALERT_PASSWORD_CHANGED = 'password_changed';

    public const ALERT_PHONE_CHANGED = 'phone_changed';

    public const ALERT_EMAIL_CHANGED = 'email_changed';

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public string $alert,
        public array $params = [],
    ) {
        $this->onQueue('notification');
        $this->afterCommit();
    }

    public function handle(NotificationService $notificationService): void
    {
        $params = $this->params;

        match ($this->alert) {
            self::ALERT_PASSWORD_CHANGED => $notificationService->sendPasswordChangedEmailAlertToAddress(
                (string) ($params['email'] ?? ''),
                (string) ($params['display_name'] ?? ''),
                (string) ($params['changed_at'] ?? ''),
                (string) ($params['ip'] ?? ''),
                $params['user_agent'] ?? null,
            ),
            self::ALERT_PHONE_CHANGED => $notificationService->sendPhoneChangedEmailAlertToAddress(
                (string) ($params['email'] ?? ''),
                (string) ($params['display_name'] ?? ''),
                (string) ($params['old_phone'] ?? ''),
                (string) ($params['new_phone'] ?? ''),
                (string) ($params['changed_at'] ?? ''),
                (string) ($params['ip'] ?? ''),
                $params['user_agent'] ?? null,
            ),
            self::ALERT_EMAIL_CHANGED => $notificationService->sendEmailChangedEmailAlertToAddress(
                (string) ($params['old_email'] ?? ''),
                (string) ($params['new_email'] ?? ''),
                (string) ($params['display_name'] ?? ''),
                (string) ($params['changed_at'] ?? ''),
                (string) ($params['ip'] ?? ''),
                $params['user_agent'] ?? null,
            ),
            default => Log::warning('[账号安全提醒] 未知提醒类型，已忽略', ['alert' => $this->alert]),
        };
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[账号安全提醒] 队列任务失败', [
            'alert' => $this->alert,
            'message' => $exception->getMessage(),
            'exception' => $exception::class,
        ]);
    }
}
