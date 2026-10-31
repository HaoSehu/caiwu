<?php

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'idc'),
            'username' => env('DB_USERNAME', 'idc'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'timezone' => env('DB_TIMEZONE', '+08:00'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    // mysqldump 可执行文件路径，管理端数据库备份使用。留空时按 DatabaseStatusService
    // 内置候选路径依次探测（含宝塔常见安装路径）。config:cache 后 env() 不再生效，
    // 必须通过本项传递，不能指望运行时读取 .env。
    'mysqldump_path' => env('MYSQLDUMP_PATH'),

    'redis' => (static function (): array {
        // 三个库共用同一实例的地址与口令，只有库号与超时不同。
        // default=队列/锁，cache=业务缓存，volatile=高频短 TTL 临时态。
        $endpoint = [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => (int) env('REDIS_PORT', 6379),
        ];

        return [
            'client' => env('REDIS_CLIENT', 'phpredis'),

            'options' => [
                'cluster' => env('REDIS_CLUSTER', 'redis'),
                // Redis 前缀用于队列和 Redis 直接操作，缓存使用 cache.php 的前缀。
                // 多实例共用同一 Redis 时必须非空，否则同名键互相覆盖。
                'prefix' => env('REDIS_PREFIX', ''),
                'persistent' => (bool) env('REDIS_PERSISTENT', false),
            ],

            'default' => $endpoint + [
                'database' => (int) env('REDIS_DB', 0),
                'timeout' => (float) env('REDIS_TIMEOUT', 2),
                'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 10),
            ],

            'cache' => $endpoint + [
                'database' => (int) env('REDIS_CACHE_DB', 1),
                'timeout' => (float) env('REDIS_TIMEOUT', 2),
                'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 10),
            ],

            // volatile 用更短的超时：临时态读写失败应当快速回退，不拖慢主请求。
            'volatile' => $endpoint + [
                'database' => (int) env('REDIS_VOLATILE_DB', 2),
                'timeout' => (float) env('REDIS_TIMEOUT', 1),
                'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 5),
            ],
        ];
    })(),
];
