<?php

declare(strict_types=1);

namespace App\Services\Upstream;

final class ProviderKey
{
    public const HOSTING_PANEL_API = 'hosting_panel_api';

    public const MOFANG_FINANCE_API = 'mofang_finance_api';

    public static function label(string $key): string
    {
        return match ($key) {
            self::HOSTING_PANEL_API => '主机面板接口',
            default => $key,
        };
    }
}
