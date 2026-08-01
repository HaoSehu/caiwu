<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Mail\DemoMail\Lib;

use App\Services\Mail\BaseMailPluginService;
use Illuminate\Support\Facades\Log;

class DemoMailService extends BaseMailPluginService
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function handleTestSmtp(string $action, array $payload): array
    {
        $result = parent::handleTestSmtp($action, $payload);
        $result['data']['account_index'] = (int) ($payload['account_index'] ?? 0);

        return $result;
    }
}
