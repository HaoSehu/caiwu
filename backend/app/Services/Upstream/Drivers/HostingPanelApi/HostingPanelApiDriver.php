<?php

declare(strict_types=1);

namespace App\Services\Upstream\Drivers\HostingPanelApi;

use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderKey;

final class HostingPanelApiDriver implements UpstreamDriver
{
    public function __construct(
        private readonly HostingPanelApiTransport $transport,
    ) {}

    public function key(): string
    {
        return ProviderKey::HOSTING_PANEL_API;
    }

    public function label(): string
    {
        return '主机面板接口';
    }

    public function supports(string $capability): bool
    {
        return $this->transport instanceof $capability;
    }

    public function resolve(string $capability): ?object
    {
        return $this->supports($capability) ? $this->transport : null;
    }
}
