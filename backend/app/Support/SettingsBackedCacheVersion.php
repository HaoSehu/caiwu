<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 以 settings 表自增整数作为跨进程缓存版本号的公共实现。
 *
 * current() 优先读缓存、缺失时回源 settings 表；bump() 在事务中
 * 行锁递增并回填缓存。子类只需声明 settings 键与缓存键。
 */
abstract class SettingsBackedCacheVersion
{
    abstract protected static function settingsGroup(): string;

    abstract protected static function settingsItem(): string;

    abstract protected static function cacheKey(): string;

    public static function current(): int
    {
        $cached = Cache::get(static::cacheKey());
        if (is_numeric($cached) && (int) $cached >= 1) {
            return (int) $cached;
        }

        $persisted = max(
            1,
            (int) DB::table('settings')
                ->where('group_key', static::settingsGroup())
                ->where('item_key', static::settingsItem())
                ->value('item_value')
        );

        Cache::put(static::cacheKey(), $persisted, now()->addYear());

        return $persisted;
    }

    public static function bump(): int
    {
        $nextVersion = DB::transaction(function (): int {
            $row = DB::table('settings')
                ->where('group_key', static::settingsGroup())
                ->where('item_key', static::settingsItem())
                ->lockForUpdate()
                ->first(['item_value']);

            $currentVersion = $row
                ? max(1, (int) ($row->item_value ?? 1))
                : 1;
            $nextVersion = $currentVersion + 1;

            DB::table('settings')->updateOrInsert(
                [
                    'group_key' => static::settingsGroup(),
                    'item_key' => static::settingsItem(),
                ],
                [
                    'item_value' => (string) $nextVersion,
                ]
            );

            return $nextVersion;
        });

        Cache::put(static::cacheKey(), $nextVersion, now()->addYear());

        return $nextVersion;
    }
}
