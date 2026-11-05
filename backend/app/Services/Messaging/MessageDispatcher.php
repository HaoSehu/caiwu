<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Exceptions\BusinessException;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\MessageDeliveryAttemptLog;
use App\Models\MessageLog;
use App\Models\MessageRecipient;
use App\Services\Messaging\Data\TransportResult;
use App\Support\MessageGroups;
use App\Support\MessageTemplateRenderer;
use App\Support\MessageTypeCatalog;
use App\Support\MessageVariables;
use App\Support\SchemaMetadataCache;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
 * 站内消息（message 渠道）不在这里写：站内信统一由
 * UserNotificationService::createForScene 落库（它同样读取场景的 message 渠道开关），
 * 若本类也写一遍，同一业务事件会生成两条站内信。管理员受众没有站内信载体，
 * 更不能以管理员 id 写入 user_notifications。
 *
 * 抛异常语义：
 * - failed（明确失败，厂商未受理）：抛出，调用方按原逻辑处理（定时任务回滚幂等键、
 *   队列任务重试）。这类失败重发是安全的，不会重复触达。
 * - unknown（结果未知，可能已发出）：**不抛**。项目硬约束要求结果未知一律不重试，
 *   否则 SendTemplateEmailJob（tries=3）与定时任务的 forgetRecord 会把同一封邮件
 *   重发 2–3 次。只记 warning 并返回 true（对调用方而言本次投递已结束），
 *   投递记录本身保留 unknown 终态供审计。
 */
class MessageDispatcher
{
    /**
     * 场景模板请求级缓存：键为 `scene|channel|templateCode`，值为模板行或 null（未挂载/已停用）。
     *
     * 批量提醒（一天几十张账单）会为每封邮件重复查询同一模板，缓存后每进程每模板只查一次。
     *
     * @var array<string, object|null>
     */
    private array $sceneTemplateCache = [];

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
     * @param  string  $templateCode  场景内的具体模板编号（同一场景多档提醒时用于取对应正文）；
     *                                为空时按渠道取场景关联的第一条启用模板
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
        string $templateCode = '',
    ): bool {
        $type = MessageTypeCatalog::get($audience, $scene);
        $stored = $this->configurations->get($audience, $scene);

        // 与 MessagePublisher 共用同一套兜底口径（站点名称/Logo、收件人名称），
        // 两条发送路径的渲染值必须一致。
        $values = MessageVariables::resolve($variables, $type['variables'], (string) ($recipients[0]['name'] ?? ''));

        // 站内消息由 UserNotificationService::createForScene 统一落库，这里不展开 message 渠道。
        $targets = $this->expandTargets($audience, $type, $stored['configurations'], $recipients, $values, $templateCode);

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

            // 重放幂等：同一 event_key 的重试/重入只补发尚未成功的渠道，
            // 已 accepted/delivered 的邮件与短信不重复触达用户。
            if (in_array($delivery->status, [MessageDelivery::STATUS_ACCEPTED, MessageDelivery::STATUS_DELIVERED], true)) {
                continue;
            }

            $startedAt = hrtime(true);
            try {
                $result = $this->transport->send($target['channel'], $delivery->request_key, $target['payload']);
            } catch (Throwable) {
                $result = TransportResult::unknown('transport_outcome_unknown');
            }

            $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $this->finishSync($delivery, $result, $durationMs);
            $this->writeAuditLog($scene, $target, $result, $delivery->request_key);

            // 只有 failed（厂商明确未受理）才抛：调用方重试是安全的，不会重复触达。
            // unknown 表示可能已发出，抛出去会让队列/定时任务重发，违反「结果未知一律不重试」；
            // 这里只留痕，投递记录保留 unknown 终态供审计与人工核对。
            if ($result->status === 'failed') {
                $failures[] = $result->error !== '' ? $result->error : 'send_failed';
            } elseif ($result->status !== 'accepted' && $result->status !== 'delivered') {
                Log::warning('[消息发送] 投递结果未知，按不重试处理', [
                    'scene' => $scene,
                    'channel' => (string) $target['channel'],
                    'error' => $result->error,
                ]);
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
    private function expandTargets(string $audience, array $type, array $configurations, array $recipients, array $values, string $templateCode = ''): array
    {
        $targets = [];
        $seen = [];

        foreach (MessageGroups::fromStored($audience, $type, $configurations) as $group) {
            if (! MessageGroups::enabled($group)) {
                continue;
            }

            foreach (MessageGroups::channelsFor((string) $group['kind']) as $channel) {
                // 站内消息统一由 UserNotificationService::createForScene 写入，这里跳过：
                // 1) 避免同一事件写两条站内信；2) 管理员受众没有站内信载体，message 渠道
                // 若被启用会把内部通知以管理员 id 写进编号相同的普通用户收件箱。
                if ($channel === 'message') {
                    continue;
                }

                $config = $group['configurations'][$channel] ?? [];
                if (($config['enabled'] ?? false) !== true) {
                    continue;
                }

                // 同一场景多档提醒（账单第 1/2/3 次、续费第 1/2 次）共用一份渠道配置，
                // 指定模板编号时以该模板的正文/主题/运营商模板号为准。
                $config = $this->applySceneTemplate($config, $channel, (string) ($type['code'] ?? ''), $templateCode);

                foreach ($recipients as $recipient) {
                    $address = $channel === 'sms' ? (string) ($recipient['mobile'] ?? '') : (string) ($recipient['email'] ?? '');
                    if (trim($address) === '') {
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
                            'title' => MessageTemplateRenderer::render((string) ($config['title'] ?? ''), $values),
                            'body' => MessageTemplateRenderer::render((string) ($config['body'] ?? ''), $values),
                            'template_id' => (string) ($config['template_id'] ?? ''),
                            // 本次实际使用的模板编号（多档提醒时由调用方指定），审计日志据此落真实模板码
                            'template_code' => $templateCode,
                            'parameters' => $this->renderParameters($this->decodeParameters($config), $values),
                            'channel_id' => (int) ($config['channel_id'] ?? 0),
                            'channel_revision' => (int) ($config['channel_revision'] ?? 0),
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

    /**
     * 场景内指定模板时，用模板表的主题/正文/运营商模板号覆盖渠道配置。
     *
     * 背景：账单支付提醒（100011/100012/100013）与续费提醒（100018/100019）是
     * 同一场景的多档模板，场景配置只能保存一份正文；回填时取了其中一档，
     * 导致「第 1 次提醒」发出「第 3 次支付提醒」的标题。指定 templateCode 后
     * 按模板取正文，配置仍决定渠道启停与通道。
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function applySceneTemplate(array $config, string $channel, string $scene, string $templateCode): array
    {
        $templateCode = trim($templateCode);
        if ($templateCode === '' || $scene === '' || ! in_array($channel, ['email', 'sms'], true)) {
            return $config;
        }

        // 请求级缓存：批量提醒时同一模板被反复查询（每封邮件/短信 2 条 SQL）。
        // 键含场景，同一模板码挂在不同场景时互不串用。
        $cacheKey = $scene.'|'.$channel.'|'.$templateCode;
        if (array_key_exists($cacheKey, $this->sceneTemplateCache)) {
            $template = $this->sceneTemplateCache[$cacheKey];
        } else {
            $template = $this->loadSceneTemplate($channel, $scene, $templateCode);
            $this->sceneTemplateCache[$cacheKey] = $template;
        }

        if ($template === null) {
            return $config;
        }

        if ($channel === 'email' && trim((string) $template->subject) !== '') {
            $config['title'] = (string) $template->subject;
        }
        if (trim((string) $template->content) !== '') {
            $config['body'] = (string) $template->content;
        }
        if ($channel === 'sms' && trim((string) ($template->provider_template_id ?? '')) !== '') {
            $config['template_id'] = (string) $template->provider_template_id;
        }

        return $config;
    }

    /**
     * 读取场景内指定模板；模板未挂到该场景或已停用时返回 null。
     *
     * 模板必须真的挂在该场景下，避免模板码写错时跨场景取正文。
     */
    private function loadSceneTemplate(string $channel, string $scene, string $templateCode): ?object
    {
        try {
            if (SchemaMetadataCache::hasTable('message_scene_templates')) {
                $linked = DB::table('message_scene_templates')
                    ->where('scene_code', $scene)
                    ->where('channel', $channel)
                    ->where('template_code', $templateCode)
                    ->exists();
                if (! $linked) {
                    return null;
                }
            }

            return DB::table('notification_templates')
                ->where('channel', $channel)
                ->where('code', $templateCode)
                ->where('is_enabled', 1)
                ->first(['subject', 'content', 'provider_template_id']);
        } catch (Throwable) {
            // 模板查询失败时沿用配置正文，不阻断发送
            return null;
        }
    }

    /**
     * 渠道模板变量：管理端存的是 JSON 字符串，历史配置可能缺失或为空串。
     * 解析失败按「无变量」处理——模板变量缺失不应让整条消息（含其它渠道）失败。
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function decodeParameters(array $config): array
    {
        $raw = $config['variables'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }

        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            Log::warning('[消息发送] 渠道模板变量不是合法 JSON，已按空变量处理', [
                'variables' => mb_substr($raw, 0, 120),
            ]);

            return [];
        }

        return $decoded;
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
     * @param  array<string, mixed>  $templates
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private function renderParameters(array $templates, array $values): array
    {
        $parameters = [];
        foreach ($templates as $key => $template) {
            $parameters[(string) $key] = MessageTemplateRenderer::render((string) $template, $values);
        }

        return $parameters;
    }

    /**
     * 管理端「邮件/短信日志」审计：新链路不再经过 NotificationService，
     * 必须自行写 message_logs，否则账单提醒、付款成功、退款等通知在
     * 管理端日志页不可见（项目红线：管理端必须能看到完整审计信息）。
     *
     * 写入失败不阻断发送：审计缺一条日志不应让业务通知失败。
     * 同一 request_key 重试时复用同一条日志行，避免重试刷出多条审计。
     *
     * @param  array<string, mixed>  $target
     */
    private function writeAuditLog(string $scene, array $target, TransportResult $result, string $requestKey): void
    {
        $channel = (string) $target['channel'];
        if (! in_array($channel, ['email', 'sms'], true)) {
            return;
        }

        $payload = (array) ($target['payload'] ?? []);
        $deliveryKey = strlen($requestKey) <= 36 ? $requestKey : null;
        // 审计的模板码优先取真实模板编号：payload['template_code'] 由 applySceneTemplate /
        // SendTemplateEmailJob 传入；email 渠道的配置 template_id 通常为空，若退化为场景编码，
        // 管理端按 100xxx 模板码检索时查不到这些日志（旧链路落的就是真实模板码）。
        $templateCode = (string) ($payload['template_code'] ?? '');
        if ($templateCode === '') {
            $templateCode = (string) ($payload['template_id'] ?? '') !== ''
                ? (string) $payload['template_id']
                : $scene;
        }

        $attributes = [
            'status' => $result->isAccepted() ? 'success' : 'failed',
            'error_msg' => $result->isAccepted() ? null : ($result->error !== '' ? $result->error : 'send_failed'),
            'sent_at' => $result->isAccepted() ? now() : null,
        ];

        try {
            if ($deliveryKey !== null) {
                $updated = MessageLog::query()
                    ->where('channel', $channel)
                    ->where('delivery_key', $deliveryKey)
                    ->update($attributes);

                if ($updated > 0) {
                    return;
                }
            }

            MessageLog::create(array_merge($attributes, [
                'channel' => $channel,
                'recipient' => (string) ($payload['address'] ?? ''),
                'template_code' => $templateCode,
                'subject' => (string) ($payload['title'] ?? ''),
                'content' => (string) ($payload['body'] ?? ''),
                'params_json' => is_array($payload['parameters'] ?? null) ? $payload['parameters'] : [],
                'origin_type' => $channel.'_send',
                'origin_id' => 0,
                'delivery_key' => $deliveryKey,
            ]));
        } catch (UniqueConstraintViolationException) {
            // 并发下两个请求同时未命中 UPDATE 后各自 INSERT：唯一索引
            // (channel, delivery_key) 拦下后到的一条，补一次更新即可，不产生重复审计行。
            if ($deliveryKey !== null) {
                try {
                    MessageLog::query()
                        ->where('channel', $channel)
                        ->where('delivery_key', $deliveryKey)
                        ->update($attributes);
                } catch (Throwable) {
                    // 补更新失败同样不影响发送
                }
            }
        } catch (Throwable $exception) {
            Log::warning('[消息发送] 审计日志写入失败，已跳过', [
                'scene' => $scene,
                'channel' => $channel,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
