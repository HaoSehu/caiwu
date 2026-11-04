<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Exceptions\BusinessException;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryAttemptLog;
use App\Models\MessageRecipient;
use App\Services\Messaging\Data\TransportResult;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use App\Support\SiteConfigPayload;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 同步消息发送器：业务代码直接调用，发送结果当场返回。
 *
 * 与 MessagePublisher（异步：落 outbox + 队列投递）的分工：
 * - 本类用于「调用方需要立即知道成功/失败」的场景（计费自动化、工单通知、管理员通知）；
 * - messages / message_recipients / message_deliveries 仍完整落库，但投递状态直接写终态，
 *   不进入 pending 队列，因此不依赖 messages:dispatch。
 *
 * 与旧 NotificationService::sendTemplateEmail 的差异：
 * - 模板来源改为「场景配置 + 场景关联模板」，渠道启停由配置决定；
 * - 收件人由调用方显式给出（不再读模板上的 audience）；
 * - 渲染、渠道选择、传输、日志全部收敛到本模块。
 *
 * 抛异常语义与旧实现一致：任一渠道明确失败即抛出，调用方按原逻辑处理。
 */
class MessageDispatcher
{
    public function __construct(
        private readonly MessageConfigurationRepository $configurations,
        private readonly MessageTransport $transport,
        private readonly MessageChannelCapabilityResolver $capabilities,
    ) {}

    /**
     * 同步发送一条场景消息给指定收件人。
     *
     * @param  list<array{audience?: string, account_id?: int, target_key?: string, email?: string, mobile?: string, name?: string}>  $recipients
     * @param  array<string, scalar|null>  $variables
     * @return bool 是否至少命中一个启用渠道；没有启用渠道时返回 false（不抛异常）
     *
     * @throws BusinessException 场景不存在
     * @throws \RuntimeException 明确发送失败
     */
    public function sendNow(
        string $eventKey,
        string $scene,
        string $businessType,
        int $businessId,
        array $recipients,
        array $variables,
        string $audience = 'customer',
    ): bool {
        $type = MessageTypeCatalog::get($audience, $scene);
        $stored = $this->configurations->get($audience, $scene);

        $values = $this->filterVariables($variables, $type['variables']);
        $values['site_name'] = $values['site_name'] !== ''
            ? $values['site_name']
            : (string) (SiteConfigPayload::payload()['site_name'] ?? SiteConfigPayload::DEFAULT_SITE_NAME);
        $values['customer_name'] = $values['customer_name'] !== ''
            ? $values['customer_name']
            : (string) ($recipients[0]['name'] ?? '客户');

        $targets = $this->expandTargets($audience, $type, $stored['configurations'], $recipients, $values);

        // 插件能力闸门：当前绑定的短信插件不支持该场景的短信能力时，跳过短信渠道。
        // 例如阿里云号码认证插件只声明 verify_code，业务通知类短信不应发出。
        if ($targets !== [] && ! $this->capabilities->smsSupportsScene($scene)) {
            $targets = array_values(array_filter(
                $targets,
                static fn (array $target): bool => $target['channel'] !== 'sms'
            ));
        }

        if ($targets === []) {
            return false;
        }

        $messageId = $this->recordMessage($eventKey, $scene, $businessType, $businessId);
        $failures = [];

        foreach ($targets as $target) {
            [$recipient, $delivery] = $this->recordTarget($messageId, $target);

            $startedAt = hrtime(true);
            try {
                $result = $this->transport->send($target['channel'], $delivery->request_key, $target['payload']);
            } catch (Throwable) {
                $result = TransportResult::unknown('transport_outcome_unknown');
            }

            $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $this->finishSync($delivery, $result, $durationMs);

            if ($result->status === 'failed') {
                $failures[] = $result->error !== '' ? $result->error : 'send_failed';
            }
        }

        if ($failures !== []) {
            throw new \RuntimeException('消息发送失败：'.implode(',', array_unique($failures)));
        }

        return true;
    }

    /**
     * 按场景配置的接收组展开「收件人 × 渠道」，与 MessagePublisher 的展开规则一致，
     * 但收件人来自调用方显式传入而不是接收组策略。
     *
     * @param  array<string, mixed>  $type
     * @param  array<string, mixed>  $configurations
     * @param  list<array<string, mixed>>  $recipients
     * @param  array<string, string>  $values
     * @return list<array{channel: string, channel_name: string, group_key: string, group_name: string, recipient: array<string, mixed>, payload: array<string, mixed>}>
     */
    private function expandTargets(string $audience, array $type, array $configurations, array $recipients, array $values): array
    {
        $targets = [];
        $seen = [];

        foreach (MessageGroups::fromStored($audience, $type, $configurations) as $group) {
            if (! MessageGroups::enabled($group)) {
                continue;
            }

            foreach (MessageGroups::channelsFor((string) $group['kind']) as $channel) {
                $config = $group['configurations'][$channel] ?? [];
                if (($config['enabled'] ?? false) !== true) {
                    continue;
                }

                foreach ($recipients as $recipient) {
                    $address = $channel === 'sms' ? (string) ($recipient['mobile'] ?? '') : (string) ($recipient['email'] ?? '');
                    if ($channel !== 'message' && trim($address) === '') {
                        continue;
                    }

                    // 站内消息必须落到具体账号：只给了邮箱/手机号（如队列任务的
                    // account_id=0）时无法写入 user_notifications，展开阶段就跳过，
                    // 否则会生成一个必然失败的目标，把整条消息连带邮件一起判为失败。
                    if ($channel === 'message' && (int) ($recipient['account_id'] ?? 0) <= 0) {
                        continue;
                    }

                    $targetKey = (string) ($recipient['target_key'] ?? ('user:'.(int) ($recipient['account_id'] ?? 0)));
                    $dedupe = $targetKey.':'.$channel;
                    if (isset($seen[$dedupe])) {
                        continue;
                    }
                    $seen[$dedupe] = true;

                    $targets[] = [
                        'channel' => $channel,
                        'channel_name' => (string) ($config['channel_name'] ?? ''),
                        'group_key' => (string) ($group['id'] ?? $audience),
                        'group_name' => (string) ($group['name'] ?? ''),
                        'recipient' => $recipient,
                        'payload' => [
                            'address' => $address,
                            'title' => $this->render((string) ($config['title'] ?? ''), $values),
                            'body' => $this->render((string) ($config['body'] ?? ''), $values),
                            'template_id' => (string) ($config['template_id'] ?? ''),
                            'parameters' => $this->renderParameters((array) json_decode((string) ($config['variables'] ?? '{}'), true, 8, JSON_THROW_ON_ERROR), $values),
                            'channel_id' => (int) ($config['channel_id'] ?? 0),
                            'channel_revision' => (int) ($config['channel_revision'] ?? 0),
                            // 站内消息写入 user_notifications 所需：收件人主键、场景与跳转
                            'account_id' => (int) ($recipient['account_id'] ?? 0),
                            'scene' => (string) ($type['code'] ?? ''),
                            'link' => (string) ($config['link'] ?? ''),
                            'data' => [],
                        ],
                    ];
                }
            }
        }

        return $targets;
    }

    private function recordMessage(string $eventKey, string $scene, string $businessType, int $businessId): int
    {
        $key = hash('sha256', $eventKey.':'.$scene.':'.$businessType.':'.$businessId);

        return DB::transaction(function () use ($key, $scene, $businessType, $businessId): int {
            DB::table('messages')->insertOrIgnore([
                'event_key' => $key,
                'scene' => $scene,
                'purpose' => Message::PURPOSE_NOTIFICATION,
                'business_type' => $businessType,
                'business_id' => $businessId,
                'created_at' => now(),
            ]);

            // 加锁读：并发事务的快照可能早于对方提交，普通 SELECT 读不到刚插入的行
            $id = Message::query()->where('event_key', $key)->lockForUpdate()->value('id');

            if (! is_int($id) && ! is_numeric($id)) {
                throw new \LogicException('消息流水写入后无法读回，请检查 messages 表状态');
            }

            return (int) $id;
        });
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array{0: MessageRecipient, 1: MessageDelivery}
     */
    private function recordTarget(int $messageId, array $target): array
    {
        return DB::transaction(function () use ($messageId, $target): array {
            $recipientData = $target['recipient'];
            $recipient = MessageRecipient::query()->firstOrCreate(
                [
                    'message_id' => $messageId,
                    'audience' => (string) ($recipientData['audience'] ?? 'customer'),
                    'target_key' => (string) ($recipientData['target_key'] ?? 'user:0'),
                ],
                [
                    'account_id' => (int) ($recipientData['account_id'] ?? 0),
                    'group_key' => $target['group_key'],
                    'group_name' => $target['group_name'],
                    'created_at' => now(),
                ]
            );

            $hex = hash('sha256', $messageId.':'.$recipient->id.':'.$target['channel']);
            $requestKey = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4)
                .'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);

            $delivery = MessageDelivery::query()->firstOrCreate(
                ['recipient_id' => $recipient->id, 'channel' => $target['channel']],
                [
                    'request_key' => $requestKey,
                    'channel_name' => $target['channel_name'],
                    'payload_encrypted' => Crypt::encryptString(
                        json_encode($target['payload'], JSON_THROW_ON_ERROR)
                    ),
                    'status' => MessageDelivery::STATUS_PENDING,
                    'next_attempt_at' => now(),
                    'queue_after' => now(),
                ]
            );

            return [$recipient, $delivery];
        });
    }

    private function finishSync(MessageDelivery $delivery, TransportResult $result, int $durationMs): void
    {
        $delivery->status = $result->status === 'accepted' || $result->status === 'delivered'
            ? $result->status
            : $result->status;
        $delivery->error_code = $result->error;
        $delivery->attempts = $delivery->attempts + 1;
        $delivery->lease_key = '';
        $delivery->lease_until = null;
        $delivery->next_attempt_at = now();
        $delivery->queue_after = now();
        $delivery->save();

        MessageDeliveryAttemptLog::query()->create([
            'delivery_id' => $delivery->id,
            'attempt_no' => $delivery->attempts,
            'status' => $result->isAccepted() ? 'success' : $result->status,
            'error_code' => $result->error,
            'duration_ms' => $durationMs,
            'created_at' => now(),
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @param  list<string>  $allowed
     * @return array<string, string>
     */
    private function filterVariables(array $variables, array $allowed): array
    {
        $filtered = [];
        foreach ($allowed as $key) {
            $filtered[$key] = (string) ($variables[$key] ?? '');
        }

        return $filtered;
    }

    /**
     * @param  array<string, mixed>  $templates
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function renderParameters(array $templates, array $values): array
    {
        $parameters = [];
        foreach ($templates as $key => $template) {
            $parameters[(string) $key] = $this->render((string) $template, $values);
        }

        return $parameters;
    }

    /** @param  array<string, string>  $values */
    private function render(string $template, array $values): string
    {
        return (string) preg_replace_callback(
            '/\{\{([a-zA-Z][a-zA-Z0-9_]*)\}\}/',
            static fn (array $match): string => $values[$match[1]] ?? '',
            $template
        );
    }
}
