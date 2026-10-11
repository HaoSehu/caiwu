<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 订单新增退款投影列（退款标记批 2026-10-11）。
 *
 * 订单是账单的生命周期投影（TradeLifecycleService 唯一写者），已同步
 * paid_amount/paid_at，本次补齐退款侧同族投影：
 * - orders.refund_amount：累计已退款金额（部分退款标记逐次累加，非覆盖写）
 * - orders.refunded_at：最近一次退款标记时间（与 paid_at 对称）
 *
 * 同步修正两处列 COMMENT 使口径显式化（均为 metadata-only，不重建表）：
 * - invoices.refund_amount：原「退款金额」易误读为单次值，实为累计口径
 * - refunds.refund_method：原无注释，补齐 manual_mark 取值说明
 *
 * 不加索引：refund_amount 不参与任何查询条件（与 paid_amount 同族一致）。
 * 迁移内不回填存量：已退款历史订单新列为 0.00，回填走独立命令
 * finance:backfill-order-refund-amount（默认 dry-run）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('orders', 'refund_amount')) {
                    $table->decimal('refund_amount', 12, 2)->default('0.00')->after('paid_amount')
                        ->comment('累计已退款金额（账单退款标记投影，由 TradeLifecycleService 唯一写入）');
                }
                if (! Schema::hasColumn('orders', 'refunded_at')) {
                    $table->timestamp('refunded_at')->nullable()->after('paid_at')
                        ->comment('最近一次退款标记时间');
                }
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'refund_amount')) {
            DB::statement(
                'ALTER TABLE `invoices` MODIFY `refund_amount` decimal(12,2) DEFAULT NULL '
                ."COMMENT '累计已退款金额（含部分退款标记，逐次累加；全额退款时等于可退基数）'"
            );
        }

        if (Schema::hasTable('refunds') && Schema::hasColumn('refunds', 'refund_method')) {
            DB::statement(
                'ALTER TABLE `refunds` MODIFY `refund_method` varchar(32) CHARACTER SET utf8mb4 '
                ."COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'balance' "
                ."COMMENT '退款方式：balance=退回余额（真退款） manual_mark=线下补标记（纯标记不动资金）'"
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (Schema::hasColumn('orders', 'refund_amount')) {
                    $table->dropColumn('refund_amount');
                }
                if (Schema::hasColumn('orders', 'refunded_at')) {
                    $table->dropColumn('refunded_at');
                }
            });
        }

        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'refund_amount')) {
            DB::statement(
                "ALTER TABLE `invoices` MODIFY `refund_amount` decimal(12,2) DEFAULT NULL COMMENT '退款金额'"
            );
        }

        if (Schema::hasTable('refunds') && Schema::hasColumn('refunds', 'refund_method')) {
            DB::statement(
                'ALTER TABLE `refunds` MODIFY `refund_method` varchar(32) CHARACTER SET utf8mb4 '
                ."COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'balance'"
            );
        }
    }
};
