<?php

declare(strict_types=1);

/*
 * 财务核心重构开关。
 *
 * 一次性切换期间与切换后的短期回滚都依赖本文件，全部开关只读环境变量，
 * 修改后需 php artisan config:cache 生效。
 */

return [
    /*
     * 热备：新内核在写 finance_accounts 的同一事务内同步 user_accounts.cash_balance。
     * 切换后 7 天内保持开启，使代码回滚可立即恢复旧内核；T+7 执行 000014 删列前关闭。
     */
    'hot_standby' => (bool) env('FINANCE_HOT_STANDBY', true),

    /*
     * 账户余额 version 条件更新（CAS）冲突时的进程内重试次数与退避基数。
     * 同一用户的支付回调与主动支付可能同时到达，直接抛 409 会让用户看到无谓失败。
     */
    'version_retry_attempts' => (int) env('FINANCE_VERSION_RETRY_ATTEMPTS', 3),
    'version_retry_delay_ms' => (int) env('FINANCE_VERSION_RETRY_DELAY_MS', 20),

    'funding' => [
        /*
         * 资金批次读取模式：
         * - new：以 finance_funding_lots 为准（切换后默认）
         * - legacy：回退旧口径（仅应急，需旧代码配合）
         */
        'read_mode' => (string) env('FINANCE_FUNDING_READ_MODE', 'new'),
    ],

    'refund' => [
        /*
         * 可退额口径：legacy=旧公式，new=统一后的单套公式。
         */
        'read_mode' => (string) env('FINANCE_REFUND_READ_MODE', 'new'),

        /*
         * 交付状态守卫：off=不检查，warn=记录证据但放行，block=已交付拒绝退款。
         * 默认 block（2026-10-21 裁决）：已复现「服务仍在跑 + 全额退款」双吃，属资金损失；
         * 存量无 service_id 的账单本就查不到交付事实，放行等于保留漏洞。
         * 个别放行走「订单侧退款入口 + 二次确认」，warn/off 仅供演练或回滚窗口临时使用。
         */
        'delivery_guard' => (string) env('FINANCE_REFUND_DELIVERY_GUARD', 'block'),
    ],

    'idempotency' => [
        'enabled' => (bool) env('FINANCE_IDEMPOTENCY_ENABLED', true),

        /* 幂等记录保留天数，超期由 finance:prune-operations 清理。 */
        'ttl_days' => (int) env('FINANCE_IDEMPOTENCY_TTL_DAYS', 30),

        /*
         * processing 超过该秒数视为僵死，允许同键重试。
         * 必须大于受保护业务的最长持锁时间（退款 Cache::lock 40s、结算 30s），
         * 否则任务还在跑就被判僵死，第二次重放会并发执行同一笔资金。
         */
        'stale_processing_seconds' => (int) env('FINANCE_IDEMPOTENCY_STALE_SECONDS', 120),
    ],

    'rebuild' => [
        /*
         * finance:rebuild --execute --fresh 会物理清空新账本，属于「资金历史只增不减」
         * 红线的唯一违例路径。默认关闭，只允许演练/切换窗口显式开启。
         */
        'allow_fresh' => (bool) env('FINANCE_REBUILD_ALLOW_FRESH', false),
    ],

    'cutover' => [
        /*
         * 切换窗口收件箱：窗口内为 true 时，支付回调只落 finance_cutover_inbox，
         * 解除维护后由 finance:replay-cutover-inbox 重放。平时保持 false。
         */
        'inbox_enabled' => (bool) env('FINANCE_CUTOVER_INBOX', false),
    ],
];
