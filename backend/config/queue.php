<?php

return [
    'default' => env('QUEUE_CONNECTION', 'database'),

    'caiwu_worker_queues' => env('CAIWU_WORKER_QUEUES', 'provision,referral,notification,coupon,default'),
    'caiwu_worker_timeout' => (int) env('CAIWU_WORKER_TIMEOUT', 1200),
    'caiwu_worker_max_timeout' => (int) env('CAIWU_WORKER_MAX_TIMEOUT', 3600),
    'caiwu_worker_tries' => (int) env('CAIWU_WORKER_TRIES', 3),
    'caiwu_worker_drain_lock_ttl' => (int) env('CAIWU_WORKER_DRAIN_LOCK_TTL', 3960),

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 3900),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => env('REDIS_QUEUE_BLOCK_FOR'),
            'after_commit' => false,
        ],
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => env('DB_QUEUE_BATCHES_TABLE', 'job_batches'),
    ],

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => env('DB_QUEUE_FAILED_TABLE', 'failed_jobs'),
    ],
];
