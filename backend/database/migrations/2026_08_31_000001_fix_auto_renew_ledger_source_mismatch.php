<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 订正资金台账错挂来源：payOrderByBalance 曾把订单 ID 写进 source_type='invoice'
 * 的 INVOICE_PAYMENT 行（orders/invoices 自增序列独立，台账按 source_id 反查账单
 * 时会展示无关账单的 invoice_no/金额/服务名）。
 *
 * 锁定口径：remark = '支付订单 {order_no}' 且 user_id 与订单一致（payByBalance /
 * 组合支付写入的合法行 remark 是账单号，即使 invoice_id 与某订单 ID 撞号也不会被改写）。
 * 订单名下必须恰好一张账单才订正 source_id/origin_id 为该账单 ID；一单多账单的
 * 歧义行保守跳过，留待人工核对。
 *
 * down 不回滚：无法从现有数据还原历史错挂值，重复 up 幂等（订正后不再命中）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_transactions')
            || ! Schema::hasTable('orders')
            || ! Schema::hasTable('invoices')) {
            return;
        }

        DB::affectingStatement(<<<'SQL'
            UPDATE account_transactions AS t
            INNER JOIN orders AS o
                ON o.id = t.source_id AND o.user_id = t.user_id
            INNER JOIN invoices AS i
                ON i.order_id = o.id
            LEFT JOIN invoices AS i2
                ON i2.order_id = o.id AND i2.id <> i.id
            SET t.source_id = i.id,
                t.origin_id = i.id
            WHERE t.source_type = 'invoice'
              AND t.event_type IN ('consume', 'invoice_payment')
              AND t.remark = CONCAT('支付订单 ', o.order_no)
              AND i2.id IS NULL
        SQL);
    }

    public function down(): void
    {
        // 不可逆订正，见类注释。
    }
};
