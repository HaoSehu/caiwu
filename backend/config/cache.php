<?php

return [
    'default' => env('CACHE_STORE', env('CACHE_DRIVER', 'file')),

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
    ],

    // 固定使用英文常量前缀，避免 Str::slug() 依赖 intl 扩展导致的环境差异
    'prefix' => env('CACHE_PREFIX', 'idc_cache_'),
];
