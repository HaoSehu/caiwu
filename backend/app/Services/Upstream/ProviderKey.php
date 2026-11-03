<?php

declare(strict_types=1);

namespace App\Services\Upstream;

final class ProviderKey
{
    public const ZJMF_FINANCE_API = 'zjmf_finance_api';

    public static function label(string $key): string
    {
        return match ($key) {
            self::ZJMF_FINANCE_API => 'ZJMF 财务',
            default => $key,
        };
    }
}
