<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 彻底删除订单"开通中(2)/已完成(3)"内部子状态：
 * 订单状态收敛为 0待支付/1已支付/4已取消/5已退款，存量 2/3 归一为 1
 * （对账层 InvoiceOrderReconciliationService 一直会把已付族订单写回 PAID，归一无语义损失），
 * 并同步 orders.status 列注释。
 *
 * 注意：状态归并不可逆，down 仅恢复列注释。
 */
return new class extends Migration
{
    private const COLUMN_TYPE = 'tinyint NOT NULL DEFAULT 0';

    private const COMMENT = '订单状态：0待支付 1已支付 4已取消 5已退款';

    private const RESTORE_COMMENT = '订单状态：0待支付 1已支付 2已支付(内部:开通中) 3已支付(内部:已完成) 4已取消 5已退款';

    public function up(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'status')) {
            DB::table('orders')
                ->whereIn('status', [2, 3])
                ->update(['status' => 1]);

            $comment = str_replace("'", "''", self::COMMENT);
            DB::statement('ALTER TABLE `orders` MODIFY `status` '.self::COLUMN_TYPE." COMMENT '{$comment}'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'status')) {
            return;
        }

        $comment = str_replace("'", "''", self::RESTORE_COMMENT);
        DB::statement('ALTER TABLE `orders` MODIFY `status` '.self::COLUMN_TYPE." COMMENT '{$comment}'");
    }
};
