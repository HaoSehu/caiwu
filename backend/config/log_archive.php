<?php

declare(strict_types=1);

return [
    // 归档协议：v1 = 旧版 pt-archiver --file --purge；v2 = 两阶段（暂存->校验->发布->清除，需生产演练批准后切换）。
    'protocol' => env('LOG_ARCHIVE_PROTOCOL', 'v1'),

    // 数据库日志统一在线 90 天，超期归档成文件（gateway 明细文件 GATEWAY_LOG_DAYS 亦为 90）。
    'retention_days' => 90,
    'file_retention_days' => 180,

    // 归档文件始终落在后端 storage 目录内，不支持 NAS 或外部挂载目录。
    'archive_root' => storage_path('app/private/log-archives'),
    'report_root' => storage_path('logs/log-archive'),

    // 可执行参数的运行时默认值；管理员后台可覆盖。
    'pt_archiver_binary' => '/usr/bin/pt-archiver',
    'pt_archiver_defaults_file' => '/etc/caiwu/pt-archiver.cnf',

    'concurrency' => 2,
    'batch_size' => 1000,
    'sleep_seconds' => 1,

    // 单次冷检索最多校验/扫描的归档行数；超出部分以 unavailable_archives
    // 显式报告，避免管理端请求把超大 CSV 读入内存或长时间占满 PHP worker。
    'cold_search_max_rows' => 50000,
    // 冷检索完整校验与窗口扫描的估算总字节预算（约三次 CSV + 一次 manifest）。
    'cold_search_max_bytes' => 128 * 1024 * 1024,

    'tables' => [
        'operation_logs' => 'API/后台操作及管理员登录日志（只读遗留表，存量由归档消化）',
        'activity_logs' => '系统与业务活动日志',
        'message_logs' => '短信/邮件统一消息日志',
        'schedule_run_logs' => 'Laravel 调度运行日志',
        'integration_plugin_runtime_logs' => '插件运行日志',
        'gateway_logs' => '支付网关交互日志（明细已按日移文件，库行按 90 天归档）',
    ],

    'excluded_tables' => [
        'archive_audit_logs',
        'account_transactions',
        'payments',
        'payment_callbacks',
        'invoices',
        'invoice_items',
        'failed_jobs',
        // 运行台账是人工重跑和审计追溯的长期记录，不得随普通日志在线删除。
        'schedule_task_runs',
        // 自动化幂等状态（recordOnce/markExecuted）不参与普通归档。
        'automation_logs',
    ],
];
