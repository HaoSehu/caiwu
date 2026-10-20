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
         * 默认 warn：存量存在 482 张无 service_id 的续费账单，直接 block 会误伤。
         */
        'delivery_guard' => (string) env('FINANCE_REFUND_DELIVERY_GUARD', 'warn'),
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
