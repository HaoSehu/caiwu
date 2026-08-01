<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Mail\Smtp\Lib;

use App\Services\Integrations\Plugins\PluginConfigRepository;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Mail\BaseMailPluginService;
use App\Services\Mail\SmtpMailTransport;

class SmtpMailService extends BaseMailPluginService
{
    public function __construct(
        private readonly PluginConfigRepository $configRepository,
        private readonly SmtpMailTransport $transport,
    ) {}

    public function key(): string
    {
        return 'smtp';
    }

    public function label(): string
    {
        return 'Single SMTP';
    }

    public function sendHtml(string $to, string $subject, string $html, array $context = []): void
    {
        $config = $this->configRepository->resolvedConfigByDomainAndSlug(PluginDomain::MAIL, 'smtp');

        $this->transport->sendHtml([
            'host' => $this->configValue($config, 'host'),
            'port' => $this->configValue($config, 'port', 465),
            'username' => $this->configValue($config, 'username'),
            'password' => $this->configValue($config, 'password'),
            'from_name' => $this->configValue($config, 'from_name', config('app.name', 'Caiwu')),
            'encryption' => $this->configValue($config, 'encryption', null),
            'timeout_seconds' => $this->configValue($config, 'timeout_seconds', 8),
        ], $to, $subject, $html);
    }
}
