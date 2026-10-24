<?php

return [
    'default' => env('QUEUE_CONNECTION', 'database'),

    // provision 单独成组：上游开通类任务（如支付后自动履约）的 timeout 可达 1200s，
    // 而 queue:work 对逗号队列列表是严格优先级消费，把它和通知/优惠券/推荐奖励串在
    // 同一个 worker 里，一个卡在上游 API 的长任务会让其余业务全部排在后面（队头阻塞）。
    'caiwu_provision_queues' => env('CAIWU_PROVISION_QUEUES', 'provision'),
    'caiwu_business_queues' => env('CAIWU_BUSINESS_QUEUES', 'referral,notification,coupon,default'),
    'caiwu_schedule_queue' => env('CAIWU_SCHEDULE_QUEUE', 'automation'),
    // 数据库备份单独成组：mysqldump 的 timeout 是 900s，而 queue:work 对逗号队列列表是
    // 严格优先级消费，混在 business 组里会让导出期间全部通知/优惠券/默认业务排队等它。
    'caiwu_backup_queue' => env('CAIWU_BACKUP_QUEUE', 'backup'),
    'caiwu_worker_timeout' => 1200,
    'caiwu_worker_max_timeout' => 3600,
    'caiwu_worker_tries' => 3,
    'caiwu_worker_drain_lock_ttl' => 3960,
    // drain 锁的存活心跳刷新间隔与判定过期阈值（秒）。
    // 锁本身的 TTL 必须覆盖最长任务（见 QueueDrainService::drainLockTtl()），进程被
    // 强杀时无法靠 finally 释放、TTL 又远未到；改由这个短周期心跳判定持有者是否已死。
    'caiwu_worker_drain_heartbeat_ttl' => 90,

    'connections' => [
        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => 3900,
            // 事务内 dispatch 的 Job 必须等提交后再入队。after_commit=false 时 worker
            // 可能在事务提交前取走任务，进程若崩溃则「任务已执行、数据已回滚」，
            // 资金类派发（返佣、履约、通知）会出现不可逆的不一致。
            'after_commit' => true,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 1500),
            'block_for' => env('REDIS_QUEUE_BLOCK_FOR'),
            'after_commit' => true,
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
