<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 账单/支付状态收敛为与订单一致的 4 态（0待支付 1已支付 4已取消 5已退款）：
 * - invoices：删除 3逾期/6部分退款，已取消 2→4；存量 3/2 归并 4（逾期单直接终态、不催不付），6 归并 1（部分退款信息保留在 Refund 表）
 * - payments：删除 2失败，已退款 3→5；存量 2 归并 4（关闭的待支付单），3 归并 5
 * 并同步两表 status 列注释；存量归并不回填 invoices.cancelled_at 与 payments.refunded_at，历史已取消/已退款记录该时间为空。
 *
 * 注意：状态归并不可逆，down 仅恢复列注释。
 */
return new class extends Migration
{
    private const COLUMN_TYPE = 'tinyint NOT NULL DEFAULT 0';

    private const INVOICE_COMMENT = '账单状态：0待支付 1已支付 4已取消 5已退款';

    private const INVOICE_RESTORE_COMMENT = '账单状态：0未付 1已付 2已取消 3逾期 5已退款 6部分退款';

    private const PAYMENT_COMMENT = '支付状态：0待支付 1已支付 4已取消 5已退款';

    private const PAYMENT_RESTORE_COMMENT = '支付状态：0待支付 1成功 2失败 3已退款 4已取消';

    public function up(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'status')) {
            DB::table('invoices')
                ->whereIn('status', [2, 3])
                ->update(['status' => 4]);
            DB::table('invoices')
                ->where('status', 6)
                ->update(['status' => 1]);

            $comment = str_replace("'", "''", self::INVOICE_COMMENT);
            DB::statement('ALTER TABLE `invoices` MODIFY `status` '.self::COLUMN_TYPE." COMMENT '{$comment}'");
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'status')) {
            DB::table('payments')
                ->where('status', 2)
                ->update(['status' => 4]);
            DB::table('payments')
                ->where('status', 3)
                ->update(['status' => 5]);

            $comment = str_replace("'", "''", self::PAYMENT_COMMENT);
            DB::statement('ALTER TABLE `payments` MODIFY `status` '.self::COLUMN_TYPE." COMMENT '{$comment}'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'status')) {
            $comment = str_replace("'", "''", self::INVOICE_RESTORE_COMMENT);
            DB::statement('ALTER TABLE `invoices` MODIFY `status` '.self::COLUMN_TYPE." COMMENT '{$comment}'");
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'status')) {
            $comment = str_replace("'", "''", self::PAYMENT_RESTORE_COMMENT);
            DB::statement('ALTER TABLE `payments` MODIFY `status` '.self::COLUMN_TYPE." COMMENT '{$comment}'");
        }
    }
};
