<?php

namespace App\Services\System;

use App\Models\EmailLog;
use App\Models\NotificationLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\EmailTemplateCatalog;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class NotificationService
{
    public const TEMPLATE_EMAIL_CODE = '100001';

    public const TEMPLATE_LOGIN_ALERT = '100002';

    public const TEMPLATE_SERVICE_RENEW_REMINDER = '100003';

    public const TEMPLATE_INVOICE_PAYMENT_REMINDER = '100004';

    public const TEMPLATE_INVOICE_OVERDUE_REMINDER = '100005';

    public const TEMPLATE_SERVICE_SUSPENDED = '100006';

    public const TEMPLATE_SERVICE_RESTORED = '100007';

    public const TEMPLATE_INVOICE_NOTICE = '100008';

    public const TEMPLATE_MANUAL_PAYMENT_CONFIRM = '100009';

    public const TEMPLATE_TICKET_CREATED = '100010';

    public const TEMPLATE_TICKET_CLIENT_REPLY = '100011';

    public const TEMPLATE_TICKET_STAFF_REPLY = '100012';

    public const TEMPLATE_ADMIN_ORDER_CREATED = '100013';

    public const TEMPLATE_ADMIN_ORDER_PAID = '100014';

    public const TEMPLATE_LOGIN_FAILURE_ALERT = '100015';

    public const TEMPLATE_LOGIN_LOCATION_ALERT = '100016';

    public const TEMPLATE_PASSWORD_CHANGED_ALERT = '100017';

    public const TEMPLATE_PHONE_CHANGED_ALERT = '100018';

    public const TEMPLATE_EMAIL_CHANGED_ALERT = '100019';

    public function sendEmail(string $to, string $subject, string $content, ?string $templateCode = null): void
    {
        $logContext = $this->createEmailLog($to, $subject, $content, $templateCode);

        try {
            if (! $this->isEmailEnabled()) {
                throw new \RuntimeException('邮件通知未启用');
            }

            $host = (string) Setting::getValue('notification', 'email_host', '');
            $port = (string) Setting::getValue('notification', 'email_port', '');
            $username = (string) Setting::getValue('notification', 'email_username', '');
            $password = (string) Setting::getValue('notification', 'email_password', '');
            $fromName = (string) Setting::getValue('notification', 'email_from_name', config('app.name', '创欧云'));

            if ($host === '' || $port === '' || $username === '' || $password === '') {
                throw new \RuntimeException('邮件接口配置不完整');
            }

            $this->configureMailer($host, $port, $username, $password, $fromName);

            Mail::html($content, function ($message) use ($to, $subject, $username, $fromName) {
                $message->to($to)->subject($subject)->from($username, $fromName);
            });

            $this->updateEmailLog($logContext, [
                'status' => 'success',
                'error_msg' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->updateEmailLog($logContext, [
                'status' => 'failed',
                'error_msg' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function sendTemplateEmail(string $to, string $templateCode, array $params = []): void
    {
        $template = EmailTemplateCatalog::find($templateCode);
        if (! is_array($template)) {
            throw new \RuntimeException('邮件模板不存在');
        }

        $subjectTemplate = (string) Setting::getValue(
            'notification',
            EmailTemplateCatalog::subjectSettingKey($templateCode),
            (string) ($template['subject'] ?? '')
        );
        $contentTemplate = (string) Setting::getValue(
            'notification',
            EmailTemplateCatalog::contentSettingKey($templateCode),
            (string) ($template['content'] ?? '')
        );

        if (trim($subjectTemplate) === '') {
            $subjectTemplate = (string) ($template['subject'] ?? '');
        }

        if (trim($contentTemplate) === '') {
            $contentTemplate = (string) ($template['content'] ?? '');
        }

        $siteName = $this->resolveSiteName();
        $renderParams = array_merge([
            'site_name' => $siteName,
        ], $this->stringifyParams($params));

        $subject = $this->renderTemplateText($subjectTemplate, $renderParams);
        $content = $this->buildThemedEmailHtml(
            $subject,
            $this->renderTemplateContent($contentTemplate, $renderParams),
            $siteName
        );

        $this->sendEmail($to, $subject, $content, $templateCode);
    }

    public function sendEmailCode(string $to, string $code): void
    {
        $this->sendTemplateEmail($to, self::TEMPLATE_EMAIL_CODE, [
            'code' => $code,
            'expire_minutes' => 10,
        ]);
    }

    public function sendLoginEmailAlertToAddress(string $email, string $displayName, string $loginAt, string $ip, ?string $userAgent = null): void
    {
        $email = trim($email);
        if ($email === '') {
            throw new \InvalidArgumentException('登录提醒邮箱不能为空');
        }

        $displayName = trim($displayName);
        if ($displayName === '') {
            $displayName = $email;
        }

        $this->sendTemplateEmail($email, self::TEMPLATE_LOGIN_ALERT, [
            'display_name' => $displayName,
            'email' => $email,
            'login_at' => $loginAt,
            'ip' => $ip,
            'device' => $this->resolveUserAgentSummary($userAgent),
        ]);
    }

    public function sendLoginEmailAlert(User $user, string $loginAt, string $ip, ?string $userAgent = null): void
    {
        $this->sendLoginEmailAlertToAddress(
            (string) $user->email,
            (string) $user->display_name,
            $loginAt,
            $ip,
            $userAgent
        );
    }

    public function sendLoginFailureEmailAlertToAddress(
        string $email,
        string $displayName,
        string $account,
        string $attemptAt,
        string $ip,
        ?string $userAgent = null
    ): void {
        $email = trim($email);
        if ($email === '') {
            throw new \InvalidArgumentException('登录失败提醒邮箱不能为空');
        }

        $displayName = trim($displayName);
        if ($displayName === '') {
            $displayName = $email;
        }

        $this->sendTemplateEmail($email, self::TEMPLATE_LOGIN_FAILURE_ALERT, [
            'display_name' => $displayName,
            'account' => trim($account),
            'attempt_at' => $attemptAt,
            'ip' => $ip,
            'device' => $this->resolveUserAgentSummary($userAgent),
        ]);
    }

    public function sendLoginLocationEmailAlertToAddress(
        string $email,
        string $displayName,
        string $loginAt,
        string $ip,
        string $previousIp,
        ?string $userAgent = null
    ): void {
        $email = trim($email);
        if ($email === '') {
            throw new \InvalidArgumentException('异地登录提醒邮箱不能为空');
        }

        $displayName = trim($displayName);
        if ($displayName === '') {
            $displayName = $email;
        }

        $this->sendTemplateEmail($email, self::TEMPLATE_LOGIN_LOCATION_ALERT, [
            'display_name' => $displayName,
            'email' => $email,
            'login_at' => $loginAt,
            'ip' => $ip,
            'previous_ip' => trim($previousIp) !== '' ? $previousIp : '无历史记录',
            'device' => $this->resolveUserAgentSummary($userAgent),
        ]);
    }

    public function sendPasswordChangedEmailAlertToAddress(
        string $email,
        string $displayName,
        string $changedAt,
        string $ip,
        ?string $userAgent = null
    ): void {
        $email = trim($email);
        if ($email === '') {
            throw new \InvalidArgumentException('密码变更提醒邮箱不能为空');
        }

        $displayName = trim($displayName);
        if ($displayName === '') {
            $displayName = $email;
        }

        $this->sendTemplateEmail($email, self::TEMPLATE_PASSWORD_CHANGED_ALERT, [
            'display_name' => $displayName,
            'changed_at' => $changedAt,
            'ip' => $ip,
            'device' => $this->resolveUserAgentSummary($userAgent),
        ]);
    }

    public function sendPhoneChangedEmailAlertToAddress(
        string $email,
        string $displayName,
        string $oldPhone,
        string $newPhone,
        string $changedAt,
        string $ip,
        ?string $userAgent = null
    ): void {
        $email = trim($email);
        if ($email === '') {
            throw new \InvalidArgumentException('手机号变更提醒邮箱不能为空');
        }

        $displayName = trim($displayName);
        if ($displayName === '') {
            $displayName = $email;
        }

        $this->sendTemplateEmail($email, self::TEMPLATE_PHONE_CHANGED_ALERT, [
            'display_name' => $displayName,
            'old_phone' => trim($oldPhone) !== '' ? $this->maskPhone($oldPhone) : '未设置',
            'new_phone' => trim($newPhone) !== '' ? $this->maskPhone($newPhone) : '未设置',
            'changed_at' => $changedAt,
            'ip' => $ip,
            'device' => $this->resolveUserAgentSummary($userAgent),
        ]);
    }

    public function sendEmailChangedEmailAlertToAddress(
        string $oldEmail,
        string $newEmail,
        string $displayName,
        string $changedAt,
        string $ip,
        ?string $userAgent = null
    ): void {
        $recipients = array_values(array_unique(array_filter([
            trim($oldEmail),
            trim($newEmail),
        ])));

        if ($recipients === []) {
            throw new \InvalidArgumentException('邮箱变更提醒邮箱不能为空');
        }

        $resolvedDisplayName = trim($displayName) !== '' ? trim($displayName) : ($recipients[0] ?? '');

        foreach ($recipients as $recipient) {
            $this->sendTemplateEmail($recipient, self::TEMPLATE_EMAIL_CHANGED_ALERT, [
                'display_name' => $resolvedDisplayName,
                'old_email' => trim($oldEmail) !== '' ? $this->maskEmail($oldEmail) : '未设置',
                'new_email' => trim($newEmail) !== '' ? $this->maskEmail($newEmail) : '未设置',
                'changed_at' => $changedAt,
                'ip' => $ip,
                'device' => $this->resolveUserAgentSummary($userAgent),
            ]);
        }
    }

    private function isEmailEnabled(): bool
    {
        $value = Setting::getValue('notification', 'email_enabled', '0');

        return in_array((string) $value, ['1', 'true', 'on'], true);
    }

    /**
     * @return array{table: 'notification_logs'|'email_logs'|null, id: int|null}
     */
    private function createEmailLog(string $to, string $subject, string $content, ?string $templateCode): array
    {
        $logContent = $this->buildEmailLogContent($content, $templateCode);

        try {
            if (Schema::hasTable('notification_logs')) {
                $log = NotificationLog::create([
                    'channel' => 'email',
                    'recipient' => $to,
                    'template_code' => $templateCode,
                    'subject' => $subject,
                    'content' => $logContent,
                    'status' => 'pending',
                    'origin_type' => 'email_send',
                    'origin_id' => 0,
                ]);

                return [
                    'table' => 'notification_logs',
                    'id' => (int) $log->getKey(),
                ];
            }

            if (Schema::hasTable('email_logs')) {
                $log = EmailLog::create([
                    'template_code' => $templateCode,
                    'to_email' => $to,
                    'subject' => $subject,
                    'content' => $logContent,
                    'status' => 'pending',
                    'error_msg' => null,
                    'sent_at' => null,
                ]);

                return [
                    'table' => 'email_logs',
                    'id' => (int) $log->getKey(),
                ];
            }
        } catch (\Throwable $exception) {
            Log::warning('邮件日志写入失败，已跳过日志写入继续发送', [
                'recipient' => $to,
                'template_code' => $templateCode,
                'message' => $exception->getMessage(),
            ]);
        }

        return [
            'table' => null,
            'id' => null,
        ];
    }

    private function buildEmailLogContent(string $content, ?string $templateCode): string
    {
        if (trim((string) $templateCode) === self::TEMPLATE_EMAIL_CODE) {
            return '邮件验证码已发送（内容已脱敏）';
        }

        return $content;
    }

    /**
     * @param  array{table: 'notification_logs'|'email_logs'|null, id: int|null}  $logContext
     * @param  array{status?: string, error_msg?: ?string, sent_at?: mixed}  $attributes
     */
    private function updateEmailLog(array $logContext, array $attributes): void
    {
        $table = $logContext['table'] ?? null;
        $id = isset($logContext['id']) ? (int) $logContext['id'] : 0;

        if ($table === null || $id <= 0) {
            return;
        }

        try {
            if ($table === 'notification_logs') {
                NotificationLog::query()->whereKey($id)->update($attributes);

                return;
            }

            if ($table === 'email_logs') {
                EmailLog::query()->whereKey($id)->update([
                    'status' => $attributes['status'] ?? 'pending',
                    'error_msg' => $attributes['error_msg'] ?? null,
                    'sent_at' => $attributes['sent_at'] ?? null,
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('邮件日志状态更新失败，已忽略以避免阻断发送流程', [
                'table' => $table,
                'id' => $id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function configureMailer(string $host, string $port, string $username, string $password, string $fromName): void
    {
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.host', $host);
        Config::set('mail.mailers.smtp.port', (int) $port);
        Config::set('mail.mailers.smtp.username', $username);
        Config::set('mail.mailers.smtp.password', $password);
        Config::set('mail.mailers.smtp.encryption', $this->resolveEncryption($port));
        Config::set('mail.mailers.smtp.timeout', $this->resolveTimeoutSeconds());
        Config::set('mail.from.address', $username);
        Config::set('mail.from.name', $fromName);

        app('mail.manager')->forgetMailers();
    }

    private function resolveEncryption(string $port): ?string
    {
        return match ((string) $port) {
            '465' => 'ssl',
            '25' => null,
            default => 'tls',
        };
    }

    private function resolveTimeoutSeconds(): int
    {
        $timeout = (int) Setting::getValue('notification', 'email_timeout_seconds', 8);

        return $timeout > 0 ? $timeout : 8;
    }

    private function resolveUserAgentSummary(?string $userAgent): string
    {
        $ua = mb_strtolower(trim((string) $userAgent));

        if ($ua === '') {
            return '未知设备';
        }

        $platform = match (true) {
            str_contains($ua, 'iphone') || str_contains($ua, 'ios') => 'iPhone',
            str_contains($ua, 'ipad') => 'iPad',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'mac os') || str_contains($ua, 'macintosh') => 'macOS',
            str_contains($ua, 'linux') => 'Linux',
            default => '未知平台',
        };

        $browser = match (true) {
            str_contains($ua, 'edg') => 'Edge',
            str_contains($ua, 'chrome') && ! str_contains($ua, 'edg') => 'Chrome',
            str_contains($ua, 'safari') && ! str_contains($ua, 'chrome') => 'Safari',
            str_contains($ua, 'firefox') => 'Firefox',
            str_contains($ua, 'micromessenger') => '微信',
            default => '未知浏览器',
        };

        return "{$platform} / {$browser}";
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) <= 7) {
            return $digits;
        }

        return substr($digits, 0, 3).'****'.substr($digits, -4);
    }

    private function maskEmail(string $email): string
    {
        $normalized = trim($email);
        if ($normalized === '' || ! str_contains($normalized, '@')) {
            return $normalized;
        }

        [$name, $domain] = explode('@', $normalized, 2);
        $nameLength = mb_strlen($name);
        if ($nameLength <= 2) {
            return mb_substr($name, 0, 1).'*@'.$domain;
        }

        return mb_substr($name, 0, 2).str_repeat('*', max($nameLength - 2, 1)).'@'.$domain;
    }

    private function stringifyParams(array $params): array
    {
        $normalized = [];

        foreach ($params as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }

            $normalized[trim($key)] = match (true) {
                is_string($value) => $value,
                is_int($value), is_float($value) => (string) $value,
                is_bool($value) => $value ? '1' : '',
                $value === null => '',
                default => (string) $value,
            };
        }

        return $normalized;
    }

    private function resolveSiteName(): string
    {
        $siteName = trim((string) Setting::getValue(
            'basic',
            'site_name',
            config('idc.site_name', config('app.name', '创欧云'))
        ));

        return $siteName !== '' ? $siteName : (string) config('app.name', '创欧云');
    }

    private function buildEmbeddedLogoSvgMarkup(): string
    {
        // 使用 FRONTEND_URL，uploads/ 目录通过 nginx 伪静态已代理到后端
        $baseUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url', '')), '/');

        // 优先使用 SVG logo，矢量格式在任何尺寸下都清晰
        $svgCandidates = ['uploads/logo/logo1.svg', 'uploads/logo/logo.svg'];
        foreach ($svgCandidates as $candidate) {
            if (is_file(public_path($candidate))) {
                $logoUrl = $baseUrl.'/'.$candidate;

                return '<img class="mail-logo" src="'.htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8').'" alt="Logo" width="63" height="44" style="display:block;width:63px;height:44px;max-width:63px;">';
            }
        }

        // 回退：内联小体积 SVG（<20KB）
        foreach (['uploads/logo/logo1.svg', 'uploads/logo/logo.svg'] as $candidate) {
            $svg = @file_get_contents(public_path($candidate));
            if (is_string($svg) && trim($svg) !== '' && strlen($svg) <= 20480) {
                $svg = preg_replace('/<\?xml[\s\S]*?\?>\s*/i', '', $svg) ?? $svg;
                $svg = preg_replace('/<svg\b([^>]*)>/i', '<svg class="mail-logo" aria-hidden="true"$1>', $svg, 1) ?? $svg;

                return trim($svg);
            }
        }

        return '';
    }

    private function renderTemplateText(string $template, array $params): string
    {
        return $this->renderTemplateWithResolver(
            $template,
            $params,
            fn (string $key) => (string) ($params[$key] ?? '')
        );
    }

    private function renderTemplateContent(string $template, array $params): string
    {
        if ($this->looksLikeHtml($template)) {
            return $this->renderTemplateWithResolver(
                $template,
                $params,
                fn (string $key) => htmlspecialchars((string) ($params[$key] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        return $this->convertPlainTextToHtml($this->renderTemplateText($template, $params));
    }

    private function renderTemplateWithResolver(string $template, array $params, callable $resolver): string
    {
        $rendered = preg_replace_callback(
            '/\{\{#([a-zA-Z0-9_]+)\}\}(.*?)\{\{\/\1\}\}/su',
            function (array $matches) use ($params) {
                $key = (string) ($matches[1] ?? '');
                $value = $params[$key] ?? '';

                return $this->hasTemplateValue($value) ? (string) ($matches[2] ?? '') : '';
            },
            $template
        );

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/u',
            fn (array $matches) => (string) $resolver((string) ($matches[1] ?? '')),
            $rendered ?? $template
        );

        if (! $this->looksLikeHtml($template)) {
            $rendered = preg_replace("/[ \t]+\n/u", "\n", (string) $rendered) ?? (string) $rendered;
            $rendered = preg_replace("/\n{3,}/u", "\n\n", (string) $rendered) ?? (string) $rendered;
        }

        return trim((string) $rendered);
    }

    private function looksLikeHtml(string $template): bool
    {
        $normalized = ltrim(trim($template));

        return preg_match('/^(<!doctype\s+html|<html\b|<body\b)/iu', $normalized) === 1
            || preg_match('/<([a-z][a-z0-9]*)(\s|>)/iu', $normalized) === 1;
    }

    private function convertPlainTextToHtml(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '<p class="mail-empty">暂无邮件内容</p>';
        }

        $blocks = preg_split("/\n{2,}/u", $content) ?: [];
        $htmlBlocks = [];

        foreach ($blocks as $block) {
            $lines = preg_split("/\n/u", trim((string) $block)) ?: [];
            $lineHtml = implode('<br>', array_map(
                fn (string $line) => htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                array_filter($lines, fn ($line) => trim((string) $line) !== '')
            ));

            if ($lineHtml !== '') {
                $htmlBlocks[] = '<p>'.$lineHtml.'</p>';
            }
        }

        return implode("\n", $htmlBlocks);
    }

    private function buildThemedEmailHtml(string $subject, string $content, string $siteName): string
    {
        $subjectHtml = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $siteNameHtml = htmlspecialchars($siteName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $summaryHtml = htmlspecialchars($this->buildEmailSummaryText($subject, $siteName), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $logoHtml = $this->buildEmbeddedLogoSvgMarkup();

        $template = <<<'HTML'
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>__SUBJECT__</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background: #f3f4f6;
      font-family: "PingFang SC", "Microsoft YaHei", Arial, sans-serif;
      color: #1f2329;
    }
    .mail-shell {
      width: 100%;
      padding: 32px 12px;
      box-sizing: border-box;
      background: #f3f4f6;
    }
    .mail-card {
      width: 100%;
      max-width: 680px;
      margin: 0 auto;
      background: #ffffff;
      border: 1px solid #cfd6e4;
      overflow: hidden;
    }
    .mail-header {
      display: flex;
      align-items: center;
      padding: 24px 28px 20px;
      border-top: 4px solid #1f4b99;
      border-bottom: 1px solid #d9e0ec;
      background: #f8fafc;
    }
    .mail-branding {
      display: flex;
      align-items: center;
      gap: 16px;
      min-width: 0;
    }
    .mail-logo {
      display: block;
      flex: 0 0 auto;
      width: auto;
      height: 44px;
      max-width: 63px;
    }
    .mail-brand {
      min-width: 0;
    }
    .mail-brand strong {
      display: block;
      font-size: 18px;
      line-height: 1.3;
      letter-spacing: 0.02em;
      color: #162033;
    }
    .mail-brand span {
      display: block;
      margin-top: 6px;
      font-size: 12px;
      color: #5b6575;
    }
    .mail-body {
      padding: 28px;
    }
    .mail-title {
      margin: 0;
      font-size: 28px;
      line-height: 1.4;
      color: #162033;
    }
    .mail-summary {
      margin: 12px 0 0;
      font-size: 14px;
      line-height: 1.8;
      color: #4b5565;
    }
    .mail-divider {
      height: 1px;
      margin: 24px 0;
      background: #d9e0ec;
    }
    .mail-content {
      font-size: 14px;
      line-height: 1.85;
      color: #1f2329;
    }
    .mail-content p {
      margin: 0 0 14px;
    }
    .mail-content p:last-child {
      margin-bottom: 0;
    }
    .mail-content strong {
      color: #162033;
    }
    .mail-content a {
      color: #1f4b99;
      text-decoration: underline;
    }
    .mail-content .mail-panel {
      margin: 18px 0;
      padding: 16px 18px;
      border: 1px solid #d9e0ec;
      background: #f8fafc;
    }
    .mail-content .mail-code {
      display: inline-block;
      margin: 8px 0 16px;
      padding: 14px 18px;
      border: 1px solid #1f4b99;
      background: #eef4ff;
      color: #1f4b99;
      font-size: 28px;
      line-height: 1;
      font-weight: 700;
      letter-spacing: 0.18em;
    }
    .mail-content .mail-kv {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      padding: 10px 0;
      border-bottom: 1px solid #d9e0ec;
    }
    .mail-content .mail-kv:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .mail-content .mail-kv:first-child {
      padding-top: 0;
    }
    .mail-content .mail-kv span {
      color: #4e5969;
      white-space: nowrap;
    }
    .mail-content .mail-kv strong {
      text-align: right;
      word-break: break-word;
    }
    .mail-content .mail-button {
      display: inline-block;
      margin-top: 12px;
      padding: 12px 18px;
      border: 1px solid #1f4b99;
      background: #1f4b99;
      color: #ffffff;
      font-weight: 600;
      text-decoration: none;
    }
    .mail-content .mail-muted,
    .mail-footer {
      color: #6b7280;
      font-size: 12px;
      line-height: 1.8;
    }
    .mail-footer {
      padding: 0 28px 28px;
    }
    .mail-empty {
      color: #86909c;
    }
    @media screen and (max-width: 640px) {
      .mail-shell {
        padding: 18px 10px;
      }
      .mail-header,
      .mail-body,
      .mail-footer {
        padding-left: 18px;
        padding-right: 18px;
      }
      .mail-title {
        font-size: 22px;
      }
      .mail-branding {
        gap: 12px;
      }
      .mail-logo {
        height: 38px;
        max-width: 54px;
      }
      .mail-content .mail-kv {
        display: block;
      }
      .mail-content .mail-kv strong {
        display: block;
        margin-top: 6px;
        text-align: left;
      }
    }
  </style>
</head>
<body>
  <div class="mail-shell">
    <div class="mail-card">
      <div class="mail-header">
        <div class="mail-branding">
          __LOGO__
          <div class="mail-brand">
            <strong>__SITE_NAME__</strong>
            <span>自动通知邮件</span>
          </div>
        </div>
      </div>
      <div class="mail-body">
        <h1 class="mail-title">__SUBJECT__</h1>
        <p class="mail-summary">__SUMMARY__</p>
        <div class="mail-divider"></div>
        <div class="mail-content">__CONTENT__</div>
      </div>
      <div class="mail-footer">
        此邮件由 __SITE_NAME__ 系统自动发送，请勿直接回复。如有疑问，请登录站点控制台或联系站内支持。
      </div>
    </div>
  </div>
</body>
</html>
HTML;

        return strtr($template, [
            '__SITE_NAME__' => $siteNameHtml,
            '__SUBJECT__' => $subjectHtml,
            '__SUMMARY__' => $summaryHtml,
            '__CONTENT__' => $content,
            '__LOGO__' => $logoHtml,
        ]);
    }

    private function buildEmailSummaryText(string $subject, string $siteName): string
    {
        $subject = trim($subject);

        return $subject !== '' ? "您收到一封来自 {$siteName} 的通知邮件，主题为：{$subject}。" : '您收到一封新的站点通知邮件。';
    }

    private function hasTemplateValue(mixed $value): bool
    {
        return ! in_array($value, [null, '', false], true);
    }
}
