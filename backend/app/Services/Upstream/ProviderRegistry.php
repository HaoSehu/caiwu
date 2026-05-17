<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Services\Upstream\Contracts\UpstreamDriver;

final class ProviderRegistry
{
    /**
     * @var array<string, UpstreamDriver>
     */
    private array $drivers = [];

    /**
     * @param  iterable<int, UpstreamDriver>  $drivers
     */
    public function __construct(iterable $drivers)
    {
        foreach ($drivers as $driver) {
            $this->drivers[$driver->key()] = $driver;
        }
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return ProviderKey::all();
    }

    /**
     * @return array<string, UpstreamDriver>
     */
    public function all(): array
    {
        return $this->drivers;
    }

    public function find(?string $key): ?UpstreamDriver
    {
        $normalizedKey = trim((string) $key);
        if ($normalizedKey === '') {
            return null;
        }

        $driverKey = match ($normalizedKey) {
            ProviderKey::MOFANG_FINANCE_API => ProviderKey::HOSTING_PANEL_API,
            default => $normalizedKey,
        };

        return $this->drivers[$driverKey] ?? null;
    }
}
