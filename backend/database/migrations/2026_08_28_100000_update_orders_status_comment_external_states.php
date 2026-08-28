<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 订单状态对外统一 4 态（待支付/已支付/已取消/已退款）：
 * 同步 orders.status 列注释，标注 2=开通中、3=已完成 为"已支付"的内部过渡子状态，
 * 消除列注释与 OrderStatus 常量、前端 shared/statusConfig 的口径漂移。
 */
return new class extends Migration
{
    private const COLUMN_TYPE = 'tinyint NOT NULL DEFAULT 0';

    private const COMMENT = '订单状态：0待支付 1已支付 2已支付(内部:开通中) 3已支付(内部:已完成) 4已取消 5已退款';

    private const RESTORE_COMMENT = '0=待付款 1=已付款 2=开通中 3=已完成 4=已取消 5=已退款';

    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'status')) {
            return;
        }

        $comment = str_replace("'", "''", self::COMMENT);
        DB::statement('ALTER TABLE `orders` MODIFY `status` '.self::COLUMN_TYPE." COMMENT '{$comment}'");
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
