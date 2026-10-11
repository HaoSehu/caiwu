<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Constants\UserNotificationType;
use App\Models\User;
use App\Services\Notification\UserNotificationService;
use App\Services\System\NotificationTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * M10 登录成功提醒·站内信通道（队列异步）。
 *
 * 邮件通道沿用既有 SendClientLoginEmailAlertJob 链路；本 Job 仅负责站内信：
 * 读取 channel=inbox code=login_success 模板渲染标题/正文后写 user_notifications
 * （type=login_alert）。发送前重新校验用户级开关（login_notify/login_email_alert），
 * 与登录响应解耦、不阻塞主流程；写入失败在 UserNotificationService 内部吞掉告警。
 */
class SendLoginAlertJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public array $backoff = [30, 120, 300];

    public function __construct(
        public int $userId,
        public string $displayName,
        public string $loginAt,
        public string $ip,
        public ?string $userAgent = null,
    ) {
        $this->afterCommit();
    }

    public function handle(
        UserNotificationService $userNotificationService,
        NotificationTemplateService $notificationTemplateService
    ): void {
        $user = User::query()->find($this->userId);
        if (! $user instanceof User) {
            return;
        }

        // 用户级开关在发送时再校验一次：登录后可能立即被用户关闭，避免骚扰。
        $loginNotifyEnabled = (bool) (($user->login_notify ?? null) ?? $user->login_email_alert);
        if (! $loginNotifyEnabled) {
            return;
        }

        $template = $notificationTemplateService->find('inbox', 'login_success');
        $content = is_array($template) && (string) ($template['content'] ?? '') !== ''
            ? (string) $template['content']
            : '您的账号于 {{login_time}} 成功登录（IP：{{login_ip}}）。如非本人操作，请立即修改登录密码。';

        $variables = [
            'nickname' => $this->displayName !== '' ? $this->displayName : (string) ($user->display_name ?? ''),
            'login_time' => $this->loginAt,
            'login_ip' => $this->ip,
            // 平台暂无 IP 归属地解析能力（与既有异地登录提醒一致），归属地统一以未知兜底。
            'login_location' => '未知',
        ];

        $title = '登录成功提醒';
        $renderedContent = str_replace(
            array_map(static fn (string $key): string => '{{'.$key.'}}', array_keys($variables)),
            array_values($variables),
            $content
        );

        $userNotificationService->create(
            $this->userId,
            UserNotificationType::LOGIN_ALERT,
            $title,
            $renderedContent,
            null,
            [
                'login_at' => $this->loginAt,
                'login_ip' => $this->ip,
                'user_agent' => $this->userAgent,
            ]
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('异步写入用户登录站内信提醒失败', [
            'user_id' => $this->userId,
            'ip' => $this->ip,
            'message' => $exception->getMessage(),
        ]);
    }
}
