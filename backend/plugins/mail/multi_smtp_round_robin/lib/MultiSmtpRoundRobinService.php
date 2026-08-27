<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Mail\MultiSmtpRoundRobin\Lib;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Mail\BaseMailPluginService;
use App\Services\Mail\SmtpMailTransport;
use Illuminate\Support\Facades\Cache;

/**
 * 多 SMTP 轮询插件：发送走轮询 + 冷却；测试流程复用基类标准骨架，
 * 仅覆写与单账号插件不同的钩子点（校验规则、正文来源、选号投递、回执字段），
 * 保证对外响应逐字维持既有契约。
 */
class MultiSmtpRoundRobinService extends BaseMailPluginService
{
    public function __construct(
        private readonly PluginConfigRepository $configRepository,
        private readonly SmtpMailTransport $transport,
    ) {}

    public function key(): string
    {
        return 'multi_smtp_round_robin';
    }

    public function label(): string
    {
        return '多 SMTP 轮询';
    }

    public function sendHtml(string $to, string $subject, string $html, array $context = []): void
    {
        $config = $this->configRepository->resolvedConfigByDomainAndSlug(PluginDomain::MAIL, 'multi_smtp_round_robin');
        $accounts = array_values(array_filter(
            is_array($config['accounts'] ?? null) ? $config['accounts'] : [],
            // filter_var 避免字符串 "false" 被 (bool) 误判为启用
            static fn (mixed $item): bool => is_array($item) && filter_var($item['enabled'] ?? true, FILTER_VALIDATE_BOOL)
        ));

        if ($accounts === []) {
            throw new BusinessException('多 SMTP 插件未配置可用账号', 42200);
        }

        $cooldownSeconds = max((int) ($config['cooldown_seconds'] ?? 60), 1);
        $accountCount = count($accounts);
        $startIndex = $this->nextIndex($accountCount);

        for ($offset = 0; $offset < $accountCount; $offset++) {
            $index = ($startIndex + $offset) % $accountCount;
            $cooldownKey = $this->cooldownKey($index);

            if ((int) Cache::get($cooldownKey, 0) > time()) {
                continue;
            }

            try {
                $this->transport->sendHtml($accounts[$index], $to, $subject, $html);
                Cache::put($this->cursorKey(), ($index + 1) % $accountCount, now()->addDay());

                return;
            } catch (\Throwable) {
                Cache::put($cooldownKey, time() + $cooldownSeconds, now()->addSeconds($cooldownSeconds));
            }
        }

        throw new BusinessException('多 SMTP 轮询发送失败，已尝试 '.$accountCount.' 个账号', 42200);
    }

    /**
     * 历史契约：send_html 不在插件侧提前校验收件人格式，交由传输层处理。
     *
     * @param  array<string, mixed>  $payload
     */
    protected function validateSendHtmlPayload(array $payload): ?string
    {
        unset($payload);

        return null;
    }

    /**
     * 历史契约：test_smtp 要求账号下标、收件人与主题齐备，
     * 参数缺失文案与单账号插件的“请填写有效的收件邮箱和主题”不同。
     *
     * @param  array<string, mixed>  $payload
     */
    protected function validateTestSmtpPayload(array $payload): ?string
    {
        $accountIndex = (int) ($payload['account_index'] ?? -1);
        $to = trim((string) ($payload['to'] ?? ''));
        $subject = trim((string) ($payload['subject'] ?? '邮箱验证码'));

        if ($accountIndex < 0 || $to === '' || $subject === '') {
            return '缺少必要参数：account_index、to、subject';
        }

        return null;
    }

    /**
     * 历史契约：测试正文仅接受 body 或内置验证码兜底文案（忽略 html 字段）。
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveTestSmtpBody(array $payload, string $code): string
    {
        return trim((string) ($payload['body'] ?? $this->verificationBody($code)));
    }

    /**
     * 测试邮件按指定下标直投：账号不存在或配置无效时抛业务异常，
     * 行为与原 execute/testSmtp 分支一致。
     *
     * @param  array<string, mixed>  $payload
     */
    protected function deliverTestSmtp(array $payload, string $to, string $subject, string $html, string $templateCode, string $code): void
    {
        unset($templateCode, $code);

        $config = $this->configRepository->resolvedConfigByDomainAndSlug(PluginDomain::MAIL, 'multi_smtp_round_robin');
        $accounts = is_array($config['accounts'] ?? null) ? array_values($config['accounts']) : [];
        $accountIndex = (int) ($payload['account_index'] ?? -1);

        if (! isset($accounts[$accountIndex])) {
            throw new BusinessException('SMTP 账号不存在', 42200);
        }

        $account = $accounts[$accountIndex];
        if (! is_array($account)) {
            throw new BusinessException('SMTP 账号配置无效', 42200);
        }

        $this->transport->sendHtml($account, $to, $subject, $html);
    }

    /**
     * 历史契约：测试回执只含 sent 与 template_code（不含 to/subject）。
     *
     * @return array<string, mixed>
     */
    protected function testSmtpSentData(string $to, string $subject, string $templateCode): array
    {
        unset($to, $subject);

        return [
            'sent' => true,
            'template_code' => $templateCode,
        ];
    }

    /**
     * 历史契约：测试成功回执携带 message 字段。
     */
    protected function testSmtpSuccessMessage(): ?string
    {
        return '测试邮件发送成功';
    }

    private function nextIndex(int $count): int
    {
        if ($count <= 1) {
            return 0;
        }

        return ((int) Cache::get($this->cursorKey(), 0)) % $count;
    }

    private function cursorKey(): string
    {
        return 'plugin:mail:multi_smtp_round_robin:cursor';
    }

    private function cooldownKey(int $index): string
    {
        return 'plugin:mail:multi_smtp_round_robin:cooldown:'.$index;
    }
}
