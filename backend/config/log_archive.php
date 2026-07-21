<?php

declare(strict_types=1);

return [
    'retention_days' => 30,
    'file_retention_days' => 180,

    'archive_root' => env('LOG_ARCHIVE_ROOT', storage_path('app/private/log-archives')),
    'report_root' => env('LOG_ARCHIVE_REPORT_ROOT', storage_path('logs/log-archive')),
    'mount_point' => env('LOG_ARCHIVE_MOUNT_POINT'),

    'pt_archiver_binary' => env('PT_ARCHIVER_BINARY', '/usr/bin/pt-archiver'),
    'pt_archiver_defaults_file' => env('PT_ARCHIVER_DEFAULTS_FILE', '/etc/caiwu/pt-archiver.cnf'),

    'concurrency' => (int) env('LOG_ARCHIVE_CONCURRENCY', 2),
    'batch_size' => (int) env('LOG_ARCHIVE_BATCH_SIZE', 1000),
    'sleep_seconds' => (int) env('LOG_ARCHIVE_SLEEP_SECONDS', 1),

    'tables' => [
        'operation_logs' => 'API/后台操作及管理员登录日志',
        'activity_logs' => '系统与业务活动日志',
        'message_logs' => '短信/邮件统一消息日志',
        'automation_logs' => '自动化任务业务日志',
        'schedule_run_logs' => 'Laravel 调度运行日志',
        'schedule_task_runs' => '平台自动任务运行日志',
        'integration_plugin_runtime_logs' => '插件运行日志',
        'gateway_logs' => '支付网关交互日志',
    ],

    'excluded_tables' => [
        'archive_audit_logs',
        'account_transactions',
        'payments',
        'payment_callbacks',
        'invoices',
        'invoice_items',
        'failed_jobs',
    ],
];
