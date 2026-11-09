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
 * 成立时才订正（订正后条件不再命中，重复 up 幂等）；一单多账单等歧义订单保守跳过，
 * 留待人工核对。
 *
 * 无账单的带折扣续费订单（建单异常残留）没有账单锚点，必须自带「已订正判定」，
 * 否则 rollback+migrate 重放（T+7 恢复演练/环境重建的既有模式）会二次扣减：
 * 以订单建单时写入快照的目录价（config_snapshot.local_renew_amount，旧版
 * config_pricing_snapshot.base_amount / meta.local_renew_amount 同值兜底）为锚——
 * 建单链路（含 2026-10-06 口径修正前的版本）总是把当时订单金额写进该快照，
 * 因此「amount 仍等于快照目录价」等价于「尚未订正」。订正后
 * amount = 目录价 − 折扣（折扣 > 0 由 WHERE 保证），判定不再命中，重放安全。
 * 无快照锚点的极早期行无法区分目录价与应付价，保守跳过、留待人工核对。
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

        // 快照列缺失（极老环境）时无「已订正判定」锚点，跳过无账单分支，留待人工核对。
        if (! Schema::hasColumn('orders', 'config_snapshot')
            || ! Schema::hasColumn('orders', 'config_pricing_snapshot')) {
            return;
        }

        DB::affectingStatement(<<<'SQL'
            UPDATE orders AS o
            LEFT JOIN invoices AS i
                ON i.order_id = o.id
            SET o.amount = ROUND(o.amount - o.discount - COALESCE(o.member_discount_amount, 0), 2)
            WHERE o.type = 'renew'
              AND (o.discount > 0 OR COALESCE(o.member_discount_amount, 0) > 0)
              AND i.id IS NULL
              AND ABS(
                  o.amount - CAST(COALESCE(
                      JSON_UNQUOTE(JSON_EXTRACT(o.config_snapshot, '$.local_renew_amount')),
                      JSON_UNQUOTE(JSON_EXTRACT(o.config_pricing_snapshot, '$.base_amount')),
                      JSON_UNQUOTE(JSON_EXTRACT(o.config_pricing_snapshot, '$.meta.local_renew_amount'))
                  ) AS DECIMAL(12,2))
              ) <= 0.005
        SQL);
    }

    public function down(): void
    {
        // 不可逆订正，见类注释。
    }
};
