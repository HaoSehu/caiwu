<?php

declare(strict_types=1);

namespace App\Integrations\Mofang\Drivers;

use App\Integrations\Mofang\Adapters\MofangFinanceAdapter;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderKey;

final class MofangFinanceDriver implements UpstreamDriver
{
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

    public function supports(string $capability): bool
    {
        return $this->adapter instanceof $capability;
    }

    public function resolve(string $capability): ?object
    {
        return $this->supports($capability) ? $this->adapter : null;
    }
}
