<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Mail\DemoMail\Lib;

use Illuminate\Support\Facades\Log;

class DemoMailService
{
    public function key(): string
    {
        return 'demo_mail';
    }

    public function label(): string
    {
        return 'Demo 邮件';
    }

    public function sendHtml(string $to, string $subject, string $html, array $context = []): void
    {
        Log::info('[demo-mail] pretend to send html mail', [
            'to' => $to,
            'subject' => $subject,
            'html_length' => strlen($html),
            'context_keys' => array_keys($context),
        ]);
    }

    public function execute(array $request): array
    {
        $action = trim((string) ($request['action'] ?? ''));
        $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];

        return match ($action) {
            'mail.send_html' => $this->sendHtmlAction($action, $payload),
            'mail.test_smtp' => $this->sendTestAction($action, $payload),
            default => ['success' => false, 'action' => $action, 'message' => 'Unsupported plugin action', 'data' => []],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendHtmlAction(string $action, array $payload): array
    {
        $this->sendHtml(
            to: (string) ($payload['to'] ?? ''),
            subject: (string) ($payload['subject'] ?? ''),
            html: (string) ($payload['html'] ?? ''),
            context: is_array($payload['context'] ?? null) ? $payload['context'] : [],
        );

        return $this->success($action, ['sent' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sendTestAction(string $action, array $payload): array
    {
        $to = (string) ($payload['to'] ?? '');
        $subject = (string) ($payload['subject'] ?? 'Plugin mail test');
        $body = (string) ($payload['body'] ?? $payload['html'] ?? '');
        $accountIndex = (int) ($payload['account_index'] ?? 0);

        $this->sendHtml(
            to: $to,
            subject: $subject,
            html: $body,
            context: ['test' => true, 'account_index' => $accountIndex],
        );

        return $this->success($action, [
            'sent' => true,
            'to' => $to,
            'subject' => $subject,
            'account_index' => $accountIndex,
        ]);
    }

    private function success(string $action, array $data): array
    {
        return ['success' => true, 'action' => $action, 'data' => $data];
    }
}
