<?php

return [
    // 默认缓存库。生产为 redis；测试由 phpunit.xml.dist 强制为 array。
    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'database' => [
            'driver' => 'database',
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'connection' => env('DB_CACHE_CONNECTION'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        // 高频短 TTL 缓存：用于节流、临时 Token、MFA 挑战、结算报价等场景，
        // 使用 DB 2 隔离，避免业务缓存的 flush/淘汰波及一次性凭证。
        // 代码侧唯一入口是 App\Support\Cache\VolatileStore，不要再写字面量。
        // connection 走 env 便于把 volatile 拆到独立实例而不改代码。
        'redis_volatile' => [
            'driver' => 'redis',
            'connection' => env('REDIS_VOLATILE_CONNECTION', 'volatile'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],
    ],

    // 缓存键前缀，留空则不加前缀。多实例共用一个 Redis 时必须设置，
    // 否则不同环境的同名键会互相覆盖；检查脚本见 backend/scripts/production_preflight.mjs。
    'prefix' => env('CACHE_PREFIX', ''),

    // 本项目刻意不使用 Cache::tags()。
    // 标签只在 redis/memcached 等支持标签的驱动上可用，一旦 CACHE_STORE 切到
    // file/database（本地、排障、降级场景）就会直接抛异常。为了保住
    // 「换驱动不改代码」这条可切换性，失效策略统一走 CacheKey 前缀 +
    // 显式 forget/版本号，而不是标签批量失效。
];
