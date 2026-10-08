<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W2 订正：新购账单 type 双值归一。
 *
 * `InvoiceService::createFromOrder` 的 type 映射此前缺 `OrderType::NEW` 分支（default 落 'normal'），
 * 而结账路径写 'new'，同一业务事件（新购）出现两种 type。补分支后，本迁移把
 * 「type='normal' 且关联订单为 new 类型」的存量账单归一为 'new'；
 * 无订单的 'normal'（createDirect 手工/普通账单）保持不变。
 * 归一后条件不再命中（幂等）；读取侧 `InvoiceType::normalize` 长期兼容 'normal'。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasTable('orders')) {
            return;
        }

        DB::affectingStatement(<<<'SQL'
            UPDATE invoices AS i
            INNER JOIN orders AS o ON o.id = i.order_id
            SET i.type = 'new', i.updated_at = NOW()
            WHERE i.type = 'normal'
              AND o.type = 'new'
        SQL);
    }

    public function down(): void
    {
        // 语义归一不可逆（'new' 与 'normal' 在订正前无可靠区分标记），不做回滚。
    }
};
