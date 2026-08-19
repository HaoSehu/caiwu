<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 站点首页聚合数据的跨进程缓存版本。
 *
 * 首页响应同时包含商品、内容和基础品牌配置。基础设置保存后递增版本，
 * 避免旧的首页聚合缓存继续返回站点名称或 Logo。
 */
class SiteHomeCacheVersion
{
    private const SETTINGS_GROUP = 'site_home';

    private const SETTINGS_ITEM = 'cache_version';

    private const DEFAULT_VERSION = 1;

    private static function cacheKey(): string
    {
        return CacheKey::siteHomeVersion();
    }

    public static function current(): int
    {
        $cached = Cache::get(self::cacheKey());
        if (is_numeric($cached) && (int) $cached >= self::DEFAULT_VERSION) {
            return (int) $cached;
        }

        $persisted = max(
            self::DEFAULT_VERSION,
            (int) DB::table('settings')
                ->where('group_key', self::SETTINGS_GROUP)
                ->where('item_key', self::SETTINGS_ITEM)
                ->value('item_value')
        );

        Cache::put(self::cacheKey(), $persisted, now()->addYear());

        return $persisted;
    }

    public static function bump(): int
    {
        $nextVersion = DB::transaction(function (): int {
            $row = DB::table('settings')
                ->where('group_key', self::SETTINGS_GROUP)
                ->where('item_key', self::SETTINGS_ITEM)
                ->lockForUpdate()
                ->first(['item_value']);

            $currentVersion = $row
                ? max(self::DEFAULT_VERSION, (int) ($row->item_value ?? self::DEFAULT_VERSION))
                : self::DEFAULT_VERSION;
            $nextVersion = $currentVersion + 1;

            DB::table('settings')->updateOrInsert(
                [
                    'group_key' => self::SETTINGS_GROUP,
                    'item_key' => self::SETTINGS_ITEM,
                ],
                [
                    'item_value' => (string) $nextVersion,
                ]
            );

            return $nextVersion;
        });

        Cache::put(self::cacheKey(), $nextVersion, now()->addYear());

        return $nextVersion;
    }
}
