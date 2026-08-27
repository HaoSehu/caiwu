<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Sms\Contracts\SmsDriver;
use App\Support\PluginDriverManager;

/**
 * 短信驱动注册表，公共注册/解析逻辑见 PluginDriverManager。
 *
 * @extends PluginDriverManager<SmsDriver>
 */
final class SmsDriverManager extends PluginDriverManager
{
    /**
     * @param  iterable<int, SmsDriver>  $drivers
     */
    public function __construct(
        iterable $drivers = [],
        ?IntegrationDriverBindingResolver $bindingResolver = null,
    ) {
        parent::__construct($drivers, $bindingResolver);
    }

    public function register(SmsDriver $driver): void
    {
        $this->registerDriver($driver);
    }

    public function resolve(?string $key = null): SmsDriver
    {
        return $this->resolveDriver($key);
    }

    protected function channelLabel(): string
    {
        return '短信';
    }

    protected function bindingCandidates(): array
    {
        return $this->bindingResolver()->smsDriverCandidates();
    }

    protected function bindingConfiguredKey(): string
    {
        return $this->bindingResolver()->smsDriverKey();
    }
}
