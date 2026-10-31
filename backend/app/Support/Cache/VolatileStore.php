<?php

declare(strict_types=1);

namespace App\Support\Cache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * 高频短 TTL 临时态缓存的唯一入口（store: redis_volatile，Redis DB 2）。
 *
 * 这一层承载 MFA 挑战、验证码、VNC token、结算报价这类「一次性、可丢、
 * 不允许占用业务缓存库」的数据。store 名只在本类出现：将来把 volatile
 * 迁到独立实例、独立 DB 或换成别的驱动，只需改 config/cache.php 与本类，
 * 不必再去 8 个 Service 里替换字面量。
 *
 * 注意：调用方一律用它拿 Repository，禁止再写 Cache::store('redis_volatile')。
 */
final class VolatileStore
{
    /** store 名与 config/cache.php 的键保持一致。 */
    public static function name(): string
    {
        return 'redis_volatile';
    }

    public static function store(): Repository
    {
        return Cache::store(self::name());
    }
}
