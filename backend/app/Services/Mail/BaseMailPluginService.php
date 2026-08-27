<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Services\System\NotificationService;
use App\Support\EmailNotificationTemplateDefaults;
use App\Support\NumericCodeNormalizer;

abstract class BaseMailPluginService
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function sendHtml(string $to, string $subject, string $html, array $context = []): void;

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];

        return match ($action) {
            'mail.send_html' => $this->handleSendHtml($action, $payload),
            'mail.test_smtp' => $this->handleTestSmtp($action, $payload),
            default => ['success' => false, 'action' => $action, 'message' => '不支持的插件动作', 'data' => []],
        };
    }

    /**
     * 发送正文邮件的标准流程：载荷校验（钩子）→ sendHtml → 统一成功回执。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function handleSendHtml(string $action, array $payload): array
    {
        $invalidReason = $this->validateSendHtmlPayload($payload);
        if ($invalidReason !== null) {
            return ['success' => false, 'action' => $action, 'message' => $invalidReason, 'data' => []];
        }

        $this->sendHtml(
            to: (string) ($payload['to'] ?? ''),
            subject: (string) ($payload['subject'] ?? ''),
            html: (string) ($payload['html'] ?? ''),
            context: is_array($payload['context'] ?? null) ? $payload['context'] : [],
        );

        return $this->success($action, ['sent' => true]);
    }

    /**
     * 测试邮件标准流程：参数校验（钩子）→ 正文组装 → 投递（钩子）→ 回执数据（钩子）。
     * 各阶段差异均通过受保护钩子声明，保证插件侧对外契约逐字不变。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function handleTestSmtp(string $action, array $payload): array
    {
        $invalidReason = $this->validateTestSmtpPayload($payload);
        if ($invalidReason !== null) {
            return ['success' => false, 'action' => $action, 'message' => $invalidReason, 'data' => []];
        }

        $to = trim((string) ($payload['to'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? '邮箱验证码'));

        $code = $this->verificationCode($payload);
        $body = $this->resolveTestSmtpBody($payload, $code);
        $templateCode = (string) ($payload['template_code'] ?? NotificationService::TEMPLATE_EMAIL_CODE);

        $html = $body !== ''
            ? nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'))
            : '<p>'.htmlspecialchars($this->verificationBody($code), ENT_QUOTES, 'UTF-8').'</p>';

        $this->deliverTestSmtp($payload, $to, $subject, $html, $templateCode, $code);

        $response = $this->success($action, $this->testSmtpSentData($to, $subject, $templateCode));

        $message = $this->testSmtpSuccessMessage();
        if ($message !== null) {
            $response['message'] = $message;
        }

        return $response;
    }

    /**
     * send_html 载荷校验：返回错误文案或 null 表示通过。
     * 默认要求收件人为合法邮箱格式（“收件人邮箱地址无效”）。
     *
     * @param  array<string, mixed>  $payload
     */
    protected function validateSendHtmlPayload(array $payload): ?string
    {
        $to = (string) ($payload['to'] ?? '');

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return '收件人邮箱地址无效';
        }

        return null;
    }

    /**
     * test_smtp 载荷校验：返回错误文案或 null 表示通过。
     * 默认要求有效收件人与主题（“请填写有效的收件邮箱和主题”）。
     *
     * @param  array<string, mixed>  $payload
     */
    protected function validateTestSmtpPayload(array $payload): ?string
    {
        $to = trim((string) ($payload['to'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? '邮箱验证码'));

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL) || $subject === '') {
            return '请填写有效的收件邮箱和主题';
        }

        return null;
    }

    /**
     * 测试邮件自定义正文的取值来源：body → html → 内置验证码兜底正文。
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveTestSmtpBody(array $payload, string $code): string
    {
        return trim((string) ($payload['body'] ?? $payload['html'] ?? $this->verificationBody($code)));
    }

    /**
     * 测试邮件投递钩子：默认经当前驱动的 sendHtml 附带测试上下文；
     * 多账号插件覆写以自行选号并直投。
     *
     * @param  array<string, mixed>  $payload  原始载荷，供选号类插件读取账号下标等参数
     */
    protected function deliverTestSmtp(array $payload, string $to, string $subject, string $html, string $templateCode, string $code): void
    {
        unset($payload);

        $this->sendHtml($to, $subject, $html, ['test' => true, 'template_code' => $templateCode, 'code' => $code]);
    }

    /**
     * 测试邮件成功回执的数据字段；多账号插件的对外字段集合与此不同，允许覆写。
     *
     * @return array<string, mixed>
     */
    protected function testSmtpSentData(string $to, string $subject, string $templateCode): array
    {
        return [
            'sent' => true,
            'to' => $to,
            'subject' => $subject,
            'template_code' => $templateCode,
        ];
    }

    /**
     * 测试邮件成功回执的附加 message 文案；单账号插件历史上不带该字段，返回 null 保持省略。
     */
    protected function testSmtpSuccessMessage(): ?string
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function success(string $action, array $data): array
    {
        return ['success' => true, 'action' => $action, 'data' => $data];
    }

    /**
     * 测试发送用的六位验证码：无效输入时以随机码兜底（共享归一化实现）。
     *
     * @param  array<string, mixed>  $payload
     */
    protected function verificationCode(array $payload): string
    {
        return NumericCodeNormalizer::normalizeSixDigit((string) ($payload['code'] ?? ''));
    }

    /**
     * 邮箱验证码兜底正文：措辞与有效时长统一取自 EmailNotificationTemplateDefaults，
     * 消除插件侧硬编码的第三份文案分叉。
     */
    protected function verificationBody(string $code): string
    {
        return str_replace(
            ['{:code}', '{:minutes}'],
            [$code, (string) EmailNotificationTemplateDefaults::EMAIL_CODE_EXPIRE_MINUTES],
            EmailNotificationTemplateDefaults::EMAIL_CODE_FALLBACK_BODY
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function configValue(array $config, string $pluginKey, mixed $default = ''): mixed
    {
        $value = $config[$pluginKey] ?? null;
        if ($value !== null && $value !== '' && $value !== []) {
            return $value;
        }

        return $default;
    }
}
