<?php

declare(strict_types=1);

namespace App\Services\Upstream;

final class ProviderKey
{
    public const HOSTING_PANEL_API = 'hosting_panel_api';

    public const MOFANG_FINANCE_API = 'mofang_finance_api';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::HOSTING_PANEL_API,
            self::MOFANG_FINANCE_API,
        ];
    }

    public static function label(string $key): string
    {
        return match ($key) {
            self::HOSTING_PANEL_API => '主机面板接口',
            self::MOFANG_FINANCE_API => '魔方财务接口',
            default => $key,
        };
    }
}
