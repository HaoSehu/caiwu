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
        // 管理员登录尝试独立审计表（成功/失败、失败原因、归纳后的客户端环境），
        // 与 activity_logs 同窗口归档；清理页 admin_login 只作用于该表。
        'admin_login_logs' => '管理员登录尝试审计（成功/失败）',
        'message_logs' => '短信/邮件统一消息日志',
        // 逐次投递尝试与 message_logs 同窗口归档；清理时先删子表再删主表。
        'message_delivery_attempts' => '消息逐次投递尝试（重试血缘）',
        'schedule_run_logs' => 'Laravel 调度运行日志',
        // 调度运行台账是全库最大日志表，需与其它日志同样支持归档；
        // 归档按 created_at 保留 90 天、归档文件保留 180 天，人工重跑血缘均在窗口内。
        'schedule_task_runs' => '调度任务运行台账（心跳/手动重跑，超期归档）',
        // tick 槽位每分钟最多一行（slot_started_at 唯一），读取方只取最新一条或当分钟槽位，
        // 超期行无任何依赖，随日志同窗口归档。
        'schedule_ticks' => '心跳调度 tick 槽位（每分钟一行，超期归档）',
        // 上游开通尝试审计：运行时只写不读（人工排查用），超期归档不破坏任何链路。
        'service_provision_attempts' => '上游开通尝试审计（只写不读，超期归档）',
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
        // 自动化幂等状态（recordOnce/markExecuted）不参与普通归档。
        'automation_logs',
    ],
];
