<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ContentPublishedCacheVersion
{
    private const CACHE_KEY = 'content:published:version';

    private const SETTINGS_GROUP = 'content';

    private const SETTINGS_ITEM = 'published_cache_version';

    private const DEFAULT_VERSION = 1;

    public static function current(): int
    {
        $cached = Cache::get(self::CACHE_KEY);
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

        Cache::forever(self::CACHE_KEY, $persisted);

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

        Cache::forever(self::CACHE_KEY, $nextVersion);

        return $nextVersion;
    }
}
