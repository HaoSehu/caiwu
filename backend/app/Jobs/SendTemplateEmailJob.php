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
use Illuminate\Support\Str;

/**
 * 模板邮件队列任务：先按模板码解析场景，再走统一消息链路同步发送。
 *
 * 历史：本任务曾直接调用 NotificationService::sendTemplateEmail。S6 切换到
 * MessageDispatcher 后，模板码 → 场景的映射集中在本类，业务派发方不感知。
 *
 * 重试语义仍由本队列任务承担（tries=3 + 退避）；MessageDispatcher 自身
 * 只做单次同步发送并且在明确失败时抛异常，供队列判定是否重试。
 *
 * deliveryKey 保留：同一任务的重试沿用同一键，MessageDispatcher 的
 * event_key 由它与模板码共同决定，因此重试不会重复落地消息流水。
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

    /** 模板码 → [场景, 受众]；未登记的模板码回退到旧发送路径 */
    private const SCENE_BY_TEMPLATE = [
        NotificationService::TEMPLATE_PAYMENT_SUCCESS => ['order_paid', 'customer'],
        NotificationService::TEMPLATE_REGISTRATION_SUCCESS => ['registration_success', 'customer'],
        NotificationService::TEMPLATE_AUTO_RENEW_NOTICE => ['auto_renew_notice', 'customer'],
        NotificationService::TEMPLATE_SERVICE_TERMINATED => ['service_terminated', 'customer'],
        NotificationService::TEMPLATE_SERVICE_UNSUSPEND_FAILED => ['service_suspend_failed', 'customer'],
        NotificationService::TEMPLATE_INVOICE_REFUND => ['order_refunded', 'customer'],
        NotificationService::TEMPLATE_SERVICE_ACTIVATED => ['service_activated', 'customer'],
        NotificationService::TEMPLATE_SERVICE_RESTORED => ['service_resumed', 'customer'],
        NotificationService::TEMPLATE_SERVICE_TRANSFERRED => ['service_transferred', 'customer'],
        // 100004「绑定通知」在场景目录里归到 security_changed（账号安全变更）
        NotificationService::TEMPLATE_ACCOUNT_BINDING_ALERT => ['security_changed', 'customer'],
        NotificationService::TEMPLATE_CLIENT_ORDER_PENDING => ['order_created', 'customer'],
        NotificationService::TEMPLATE_ADMIN_LOGIN_ALERT => ['admin_login_alert', 'admin'],
    ];

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

    public function handle(NotificationService $notificationService, MessageDispatcher $dispatcher): void
    {
        $mapping = self::SCENE_BY_TEMPLATE[$this->templateCode] ?? null;

        if ($mapping === null) {
            // 未登记场景的模板码仍走旧路径，保证行为不倒退；新增模板时补进映射表。
            $notificationService->sendTemplateEmail(
                $this->email,
                $this->templateCode,
                $this->payload,
                $this->deliveryKey,
                max(1, $this->attempts()),
            );

            return;
        }

        [$scene, $audience] = $mapping;

        $name = trim((string) ($this->payload['display_name'] ?? '')) !== ''
            ? (string) $this->payload['display_name']
            : $this->email;

        $dispatcher->sendNow(
            'template.'.$this->templateCode.'.'.$this->deliveryKey,
            $scene,
            'email',
            0,
            [[
                'audience' => $audience,
                'account_id' => 0,
                'target_key' => ($audience === 'admin' ? 'admin:email:' : 'email:').$this->email,
                'email' => $this->email,
                'mobile' => '',
                'name' => $name,
            ]],
            $this->payload,
            $audience,
            $this->templateCode
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
