<?php

declare(strict_types=1);

namespace App\Services\Sms\Contracts;

interface SmsDriver
{
    public function key(): string;

    public function label(): string;

    public function sendVerifyCode(string $phone, string $code, array $options = []): array;
}
