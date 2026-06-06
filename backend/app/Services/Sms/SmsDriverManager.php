<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Models\Setting;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\Drivers\AliyunSmsDriver;

final class SmsDriverManager
{
    /** @var array<string, SmsDriver> */
    private array $drivers = [];

    public function __construct()
    {
        $this->register(new AliyunSmsDriver);
    }

    public function register(SmsDriver $driver): void
    {
        $this->drivers[$driver->key()] = $driver;
    }

    public function resolve(?string $key = null): SmsDriver
    {
        $resolvedKey = $key ?? $this->getConfiguredKey();

        if (isset($this->drivers[$resolvedKey])) {
            return $this->drivers[$resolvedKey];
        }

        throw new \RuntimeException("短信驱动 [{$resolvedKey}] 未注册");
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
        $key = trim((string) Setting::getValue('notification', 'sms_driver', ''));

        return $key !== '' ? $key : 'aliyun';
    }
}
