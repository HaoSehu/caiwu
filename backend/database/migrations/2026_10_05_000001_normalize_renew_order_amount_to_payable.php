<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 订正续费订单金额口径：orders.amount 曾写目录价（唯一违反「订单金额 = 应付价」
 * 统一口径的链路），导致 FinanceDocumentService 组成断言失败（折后续费 + 网关
 * 支付回调死循环）、Money::catalogAmountOf 还原错误（同周期复用检查恒不成立）。
 *
 * 有账单订单以账单金额为锚，仅当「amount - 券减免 - 会员折扣 = 账单金额」恒等式
 * 成立时才订正（订正后条件不再命中，重复 up 幂等）；账单缺失的带折扣续费订单
 * 单独订正（无锚点，依赖 migrations 记录保证只跑一次，不可手工重放）；一单多账单
 * 等歧义订单保守跳过，留待人工核对。
 *
 * down 不回滚：无法从现有数据还原历史目录价，语义订正不可逆。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')
            || ! Schema::hasTable('invoices')
            || ! Schema::hasColumn('orders', 'member_discount_amount')) {
            return;
        }

        // 有账单且恒等式成立：正常目录价行，订正后不再命中（幂等）
        DB::affectingStatement(<<<'SQL'
            UPDATE orders AS o
            INNER JOIN invoices AS i
                ON i.order_id = o.id
            LEFT JOIN invoices AS i2
                ON i2.order_id = o.id AND i2.id <> i.id
            SET o.amount = ROUND(o.amount - o.discount - COALESCE(o.member_discount_amount, 0), 2)
            WHERE o.type = 'renew'
              AND (o.discount > 0 OR COALESCE(o.member_discount_amount, 0) > 0)
              AND i2.id IS NULL
              AND ABS(o.amount - o.discount - COALESCE(o.member_discount_amount, 0) - i.amount) <= 0.005
        SQL);

        // 无账单的带折扣续费订单（建单异常残留）：reconcile 自动补单会按目录价出账，
        // 一并订正；无账单锚点，不可重复执行
        DB::affectingStatement(<<<'SQL'
            UPDATE orders AS o
            LEFT JOIN invoices AS i
                ON i.order_id = o.id
            SET o.amount = ROUND(o.amount - o.discount - COALESCE(o.member_discount_amount, 0), 2)
            WHERE o.type = 'renew'
              AND (o.discount > 0 OR COALESCE(o.member_discount_amount, 0) > 0)
              AND i.id IS NULL
        SQL);
    }

    public function down(): void
    {
        // 不可逆订正，见类注释。
    }
};
