<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Mail\Contracts\MailDriver;
use App\Support\PluginDriverManager;

/**
 * 邮件驱动注册表，公共注册/解析逻辑见 PluginDriverManager。
 *
 * @extends PluginDriverManager<MailDriver>
 */
final class MailDriverManager extends PluginDriverManager
{
    /**
     * @param  iterable<int, MailDriver>  $drivers
     */
    public function __construct(
        iterable $drivers = [],
        ?IntegrationDriverBindingResolver $bindingResolver = null,
    ) {
        parent::__construct($drivers, $bindingResolver);
    }

    public function register(MailDriver $driver): void
    {
        $this->registerDriver($driver);
    }

    public function resolve(?string $key = null): MailDriver
    {
        return $this->resolveDriver($key);
    }

    protected function channelLabel(): string
    {
        return '邮件';
    }

    protected function bindingCandidates(): array
    {
        return $this->bindingResolver()->mailDriverCandidates();
    }

    protected function bindingConfiguredKey(): string
    {
        return $this->bindingResolver()->mailDriverKey();
    }
}
