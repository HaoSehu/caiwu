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
        $keys = array_keys($this->drivers);
        sort($keys);

        return $keys;
    }

    /**
     * @return array<int, array{value:string,label:string}>
     */
    public function options(): array
    {
        $drivers = $this->drivers;
        ksort($drivers);

        return array_map(
            fn (UpstreamDriver $driver): array => [
                'value' => $driver->key(),
                'label' => $driver->label(),
            ],
            array_values($drivers)
        );
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

        return $this->drivers[$normalizedKey] ?? null;
    }
}
