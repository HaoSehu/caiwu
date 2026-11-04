<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Messaging\MessageDispatcher;
use App\Services\System\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTicketNotificationEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [30, 120, 300];

    /** 工单模板码 → [场景, 受众]；未登记的回退旧发送路径 */
    private const SCENE_BY_TEMPLATE = [
        NotificationService::TEMPLATE_TICKET_OPENED => ['ticket_opened', 'customer'],
        NotificationService::TEMPLATE_TICKET_STAFF_REPLY => ['ticket_replied', 'customer'],
        NotificationService::TEMPLATE_TICKET_AUTO_CLOSED => ['ticket_auto_closed', 'customer'],
        NotificationService::TEMPLATE_TICKET_CREATED => ['admin_ticket_created', 'admin'],
        NotificationService::TEMPLATE_TICKET_CLIENT_REPLY => ['admin_ticket_replied', 'admin'],
        NotificationService::TEMPLATE_TICKET_PENDING_REMINDER => ['ticket_pending_reminder', 'customer'],
        NotificationService::TEMPLATE_TICKET_REOPENED => ['ticket_reopened', 'customer'],
    ];

    /**
     * @param  array<string, string|int|float|bool|null>  $params
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $to,
        public string $templateCode,
        public array $params = [],
        public array $context = [],
    ) {
        $this->afterCommit();
    }

    public function handle(MessageDispatcher $dispatcher): void
    {
        $scene = self::SCENE_BY_TEMPLATE[$this->templateCode] ?? null;

        if ($scene === null) {
            // 未登记场景的模板码回退旧路径，保证行为不倒退。
            app(NotificationService::class)->sendTemplateEmail($this->to, $this->templateCode, $this->params);

            return;
        }

        [$sceneCode, $audience] = $scene;

        $dispatcher->sendNow(
            'template.'.$this->templateCode.'.'.md5($this->to),
            $sceneCode,
            'ticket',
            0,
            [[
                'audience' => $audience,
                'account_id' => 0,
                'target_key' => 'email:'.$this->to,
                'email' => $this->to,
                'mobile' => '',
                'name' => (string) ($this->params['display_name'] ?? $this->to),
            ]],
            $this->params,
            $audience
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('异步发送工单通知邮件失败', array_merge($this->context, [
            'to_email' => $this->to,
            'template_code' => $this->templateCode,
            'message' => $exception->getMessage(),
        ]));
    }
}
