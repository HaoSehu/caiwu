<?php

declare(strict_types=1);

namespace App\Support;

class ContentPublishedCacheVersion extends SettingsBackedCacheVersion
{
    protected static function settingsGroup(): string
    {
        return 'content';
    }

    protected static function settingsItem(): string
    {
        return 'published_cache_version';
    }

    protected static function cacheKey(): string
    {
        return CacheKey::contentPublishedVersion();
    }
}
