<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Exceptions\BusinessException;
use App\Support\MessageGroups;
use App\Support\MessageTypeCatalog;
use App\Support\SiteConfigPayload;

/**
 * 验证码独立发送器（对齐 howstack VerificationMessageSender）。
 *
 * 与普通通知的关键差异：
 * - 同步直发，不写 messages/message_deliveries，不重试；
 * - 只从 customer 组读取场景配置，且要求模板正文含 {{code}}；
 * - 发送成功才激活挑战，失败即抛业务异常。
 *
 * 说明：caiwu 红线要求管理端可见完整验证码，因此验证码发送仍由
 * 调用方（SendAccountCodeService）走既有 SmsService/NotificationService 记录 message_logs；
 * 本类只负责「按新配置模型解析场景 + 走统一传输层」，两者在 S4 阶段并存。
 */
final class VerificationMessageSender
{
    public function __construct(
        private readonly MessageConfigurationRepository $configurations,
        private readonly MessageTransport $transport,
    ) {}

    /**
     * 返回可用于当前场景与方式的渠道配置；不可用时返回 null。
     *
     * @return array<string, mixed>|null
     */
    public function configuration(string $scene, string $method): ?array
    {
        $channel = $method === 'email' ? 'email' : 'sms';

        try {
            $type = MessageTypeCatalog::get('customer', $scene);
        } catch (BusinessException) {
            return null;
        }

        if (! in_array($channel, $type['channels'], true)) {
            return null;
        }

        $stored = $this->configurations->get('customer', $scene);

        foreach (MessageGroups::fromStored('customer', $type, $stored['configurations']) as $group) {
            $config = $group['configurations'][$channel] ?? [];

            if (($group['kind'] ?? '') !== 'customer'
                || ($group['enabled'] ?? false) !== true
                || ($config['enabled'] ?? false) !== true
                || (int) ($config['channel_id'] ?? 0) <= 0
                || ! $this->containsCodeVariable($config)) {
                continue;
            }

            return $config;
        }

        return null;
    }

    /**
     * 同步发送验证码。
     *
     * @param  array<string, mixed>  $config
     */
    public function send(string $scene, string $method, string $target, string $challengeId, string $code, array $config): bool
    {
        $variables = [
            'code' => $code,
            'expires_in' => '5',
            'site_name' => (string) (SiteConfigPayload::payload()['site_name'] ?? SiteConfigPayload::DEFAULT_SITE_NAME),
            'customer_name' => '会员',
        ];

        $parameters = [];
        foreach ((array) json_decode((string) ($config['variables'] ?? '{}'), true, 8, JSON_THROW_ON_ERROR) as $key => $template) {
            $parameters[(string) $key] = $this->render((string) $template, $variables);
        }

        $result = $this->transport->send($method === 'email' ? 'email' : 'sms', $scene.':'.$challengeId, [
            'address' => $target,
            'title' => $this->render((string) ($config['title'] ?? '验证码'), $variables),
            'body' => $this->render((string) ($config['body'] ?? ''), $variables),
            'template_id' => (string) ($config['template_id'] ?? ''),
            'parameters' => $parameters,
            'channel_id' => (int) ($config['channel_id'] ?? 0),
            'channel_revision' => (int) ($config['channel_revision'] ?? 0),
        ]);

        return $result->isAccepted();
    }

    /** @param  array<string, mixed>  $config */
    private function containsCodeVariable(array $config): bool
    {
        $raw = json_encode($config, JSON_THROW_ON_ERROR);

        return str_contains($raw, '{{code}}') || str_contains($raw, '{code}');
    }

    /** @param  array<string, string>  $variables */
    private function render(string $template, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{\{([a-zA-Z][a-zA-Z0-9_]*)\}\}/',
            static fn (array $match): string => $variables[$match[1]] ?? '',
            $template
        );
    }
}
