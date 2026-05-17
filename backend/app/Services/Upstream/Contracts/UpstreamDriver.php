<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

interface UpstreamDriver
{
    public function key(): string;

    public function label(): string;

    public function supports(string $capability): bool;

    public function resolve(string $capability): ?object;
}
