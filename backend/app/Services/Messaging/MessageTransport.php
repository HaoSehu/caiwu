<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Services\Mail\MailDriverManager;
use App\Services\Messaging\Data\DeliveryPayload;
use App\Services\Messaging\Data\SmsMessage;
use App\Services\Messaging\Data\TransportResult;
use App\Services\Notification\UserNotificationService;
use App\Services\Sms\Data\SmsMessageRequest;
use App\Services\Sms\SmsDriverManager;
use Throwable;

/**
 * 统一传输层：把已渲染载荷交给对应渠道驱动，并把各驱动结果归一到 TransportResult。
 *
 * 三条硬约束（对齐 howstack InstalledMessageTransport）：
 * 1. 结果未知一律不重试，避免重复触达；
 * 2. 只有厂商明确失败且标记可重试时 retry 才为 true；
 * 3. 本站不在这里写 message_logs —— 审计写入由调用方（发送服务）负责，
 *    传输层只返回结果，保持「一次发送只记一次日志」的单一写口。
 *
 * 站内消息是例外：它不经过外部驱动，「送达」就等于真正写入 user_notifications，
 * 因此这里必须执行写入，否则投递记录会显示成功而用户什么也收不到。
 */
final class MessageTransport
{
    public function __construct(
        private readonly MailDriverManager $mailDrivers,
        private readonly SmsDriverManager $smsDrivers,
        private readonly UserNotificationService $inbox,
    ) {}

    /**
     * 站内消息：不经过外部驱动，写入 user_notifications 即视为送达。
     *
     * payload 需要带 account_id（收件人主键）；缺失时视为无效收件人，不静默成功。
     *
     * @param  array<string, mixed>  $payload
     */
    public function deliverInbox(array $payload): TransportResult
    {
        $userId = (int) ($payload['account_id'] ?? 0);
        if ($userId <= 0) {
            return TransportResult::failed('inbox_recipient_invalid');
        }

        $title = trim((string) ($payload['title'] ?? ''));
        if ($title === '') {
            return TransportResult::failed('inbox_title_empty');
        }

        try {
            $written = $this->inbox->create(
                $userId,
                (string) ($payload['scene'] ?? 'system'),
                $title,
                (string) ($payload['body'] ?? ''),
                (string) ($payload['link'] ?? ''),
                is_array($payload['data'] ?? null) ? $payload['data'] : [],
            );
        } catch (Throwable) {
            return TransportResult::unknown('inbox_write_failed');
        }

        // create() 返回 null 表示表不可用或写入被忽略，不能当作送达
        return $written === null
            ? TransportResult::failed('inbox_write_skipped')
            : TransportResult::delivered();
    }

    /**
     * @param  array<string, mixed>  $payload  DeliveryPayload::toArray() 的结构
     */
    public function send(string $channel, string $requestKey, array $payload): TransportResult
    {
        $data = DeliveryPayload::fromArray($payload);

        return match ($channel) {
            'email' => $this->sendEmail($requestKey, $data),
            'sms' => $this->sendSms($requestKey, $data),
            'message' => $this->deliverInbox($payload),
            default => TransportResult::failed('channel_not_integrated'),
        };
    }

    private function sendEmail(string $requestKey, DeliveryPayload $payload): TransportResult
    {
        $address = mb_trim($payload->address);
        if ($address === '' || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            return TransportResult::failed('recipient_invalid');
        }

        try {
            $driver = $this->mailDrivers->resolve();
        } catch (Throwable) {
            // 通道临时解析失败：配置可能刚变更，允许重试
            return TransportResult::failed('channel_not_configured', true);
        }

        try {
            $driver->sendHtml($address, $payload->title, $payload->body, [
                'request_key' => $requestKey,
            ]);
        } catch (Throwable $exception) {
            // 驱动抛出即无法证明厂商未受理：记为未知，不重试。
            return TransportResult::unknown('transport_outcome_unknown');
        }

        return TransportResult::accepted($requestKey);
    }

    private function sendSms(string $requestKey, DeliveryPayload $payload): TransportResult
    {
        $recipient = $this->normalizePhone($payload->address);
        if ($recipient === null) {
            return TransportResult::failed('recipient_invalid');
        }

        try {
            $driver = $this->smsDrivers->resolve();
        } catch (Throwable) {
            // 通道临时解析失败：配置可能刚变更，允许重试
            return TransportResult::failed('channel_not_configured', true);
        }

        try {
            // 构造 DTO 时校验号码/模板号/变量格式；失败说明数据本身不合法，不重试。
            $message = new SmsMessage(
                $requestKey,
                $recipient,
                $payload->templateId,
                $payload->parameters,
                $payload->body,
            );
        } catch (Throwable) {
            return TransportResult::failed('message_invalid');
        }

        try {
            $result = $driver->sendMessage(new SmsMessageRequest($recipient, $payload->templateId, $payload->body, [
                'request_key' => $requestKey,
                'provider_template_id' => $payload->templateId,
                'template_variables' => $payload->parameters,
            ]));
        } catch (Throwable) {
            return TransportResult::unknown('transport_outcome_unknown');
        }

        $status = strtolower($result->status);

        return match ($status) {
            'success', 'accepted', 'sent', 'delivered' => TransportResult::accepted($result->requestId),
            // 厂商明确拒绝多为限流/风控等瞬时原因，允许有限重试
            'failed', 'rejected' => TransportResult::failed('provider_rejected', true),
            default => TransportResult::unknown('provider_status_unknown'),
        };
    }

    /** 大陆手机号补 +86 并校验 E.164；无法归一返回 null */
    private function normalizePhone(string $address): ?string
    {
        $phone = preg_replace('/[\s-]+/', '', mb_trim($address)) ?? '';

        if (preg_match('/^1[3-9][0-9]{9}$/D', $phone) === 1) {
            return '+86'.$phone;
        }

        if (preg_match('/^\+[1-9][0-9]{7,14}$/D', $phone) === 1) {
            return $phone;
        }

        return null;
    }
}
