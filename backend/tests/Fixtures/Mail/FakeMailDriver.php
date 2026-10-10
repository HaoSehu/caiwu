<?php

declare(strict_types=1);

namespace Tests\Fixtures\Mail;

use App\Services\Mail\Contracts\MailDriver;

/**
 * 共享邮件驱动替身（V7-11 收敛：新测试不再写匿名替身）。
 *
 * - 默认 key = 'smtp'（系统默认邮件驱动键）；也可构造为任意键用于候选/绑定解析用例。
 * - sendHtml 仅记录，不触网；提供 lastHtml()/lastTo()/count() 便捷读取。
 */
final class FakeMailDriver implements MailDriver
{
    /** @var list<array{to: string, subject: string, html: string, context: array<string, mixed>}> */
    public array $sent = [];

    public function __construct(private readonly string $key = 'smtp') {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return '测试邮件驱动';
    }

    public function sendHtml(string $to, string $subject, string $html, array $context = []): void
    {
        $this->sent[] = [
            'to' => $to,
            'subject' => $subject,
            'html' => $html,
            'context' => $context,
        ];
    }

    public function count(): int
    {
        return count($this->sent);
    }

    public function lastHtml(): string
    {
        $last = $this->sent === [] ? null : $this->sent[array_key_last($this->sent)];

        return (string) ($last['html'] ?? '');
    }

    public function lastTo(): string
    {
        $last = $this->sent === [] ? null : $this->sent[array_key_last($this->sent)];

        return (string) ($last['to'] ?? '');
    }
}
