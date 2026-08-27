<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 站点首页聚合数据的跨进程缓存版本。
 *
 * 首页响应同时包含商品、内容和基础品牌配置。基础设置保存后递增版本，
 * 避免旧的首页聚合缓存继续返回站点名称或 Logo。
 */
class SiteHomeCacheVersion extends SettingsBackedCacheVersion
{
    protected static function settingsGroup(): string
    {
        return 'site_home';
    }

    protected static function settingsItem(): string
    {
        return 'cache_version';
    }

    protected static function cacheKey(): string
    {
        return CacheKey::siteHomeVersion();
    }
}
