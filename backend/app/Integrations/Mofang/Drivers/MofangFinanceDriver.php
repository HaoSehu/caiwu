<?php

declare(strict_types=1);

namespace App\Integrations\Mofang\Drivers;

use App\Integrations\Mofang\Adapters\MofangFinanceAdapter;
use App\Services\Upstream\Contracts\ProvidesConsoleAccess;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesConsoleNetwork;
use App\Services\Upstream\Contracts\ProvidesConsoleRuntime;
use App\Services\Upstream\Contracts\ProvidesConsoleSecurity;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\Contracts\ProvidesStatusSync;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderKey;

final class MofangFinanceDriver implements UpstreamDriver
{
    private const CAPABILITIES = [
        ProvidesConsoleAccess::class,
        ProvidesConsoleCatalog::class,
        ProvidesConsoleNetwork::class,
        ProvidesConsoleRuntime::class,
        ProvidesConsoleSecurity::class,
        ProvidesProvisioning::class,
        ProvidesRenewal::class,
        ProvidesScheduledAuthRefresh::class,
        ProvidesStatusSync::class,
    ];

    public function __construct(
        private readonly MofangFinanceAdapter $adapter,
    ) {}

    public function key(): string
    {
        return ProviderKey::MOFANG_FINANCE_API;
    }

    public function label(): string
    {
        return '魔方财务接口';
    }

    public function capabilities(): array
    {
        return self::CAPABILITIES;
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true)
            && $this->adapter instanceof $capability;
    }

    public function resolve(string $capability): ?object
    {
        return $this->supports($capability) ? $this->adapter : null;
    }
}
