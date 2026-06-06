<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Models\Setting;
use App\Services\Verification\Contracts\VerificationDriver;
use App\Services\Verification\Drivers\Stay33Driver;

final class VerificationDriverManager
{
    /** @var array<string, VerificationDriver> */
    private array $drivers = [];

    public function __construct()
    {
        $this->register(new Stay33Driver);
    }

    public function register(VerificationDriver $driver): void
    {
        $this->drivers[$driver->key()] = $driver;
    }

    public function resolve(?string $key = null): VerificationDriver
    {
        $resolvedKey = $key ?? $this->getConfiguredKey();

        if (isset($this->drivers[$resolvedKey])) {
            return $this->drivers[$resolvedKey];
        }

        throw new \RuntimeException("实名认证驱动 [{$resolvedKey}] 未注册");
    }

    /** @return array<int, array{value: string, label: string}> */
    public function options(): array
    {
        $result = [];
        foreach ($this->drivers as $driver) {
            $result[] = ['value' => $driver->key(), 'label' => $driver->label()];
        }

        return $result;
    }

    private function getConfiguredKey(): string
    {
        $key = trim((string) Setting::getValue('verification', 'verification_driver', ''));

        return $key !== '' ? $key : 'stay33';
    }
}
