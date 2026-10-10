<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M4 归一：invoice_items.item_type 镜像所属账单 type（结构整改批 2026-10-10）。
 *
 * 唯一写入点 Invoice::syncInvoiceItemProjection() 使 item_type 恒等于账单 type
 * 当前值；2026_10_07_000002 只归一了 invoices 一侧，明细侧遗留 110 行
 * 「账单 type='new' 而 item_type='normal'」。读侧对 'new'/'normal' 零分支
 * （rg 实证），本迁移补齐明细侧，归一后两者逐行一致（幂等，可重跑）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || ! Schema::hasTable('invoice_items')) {
            return;
        }

        DB::affectingStatement(<<<'SQL'
            UPDATE invoice_items AS ii
            INNER JOIN invoices AS i ON i.id = ii.invoice_id
            SET ii.item_type = 'new', ii.updated_at = NOW()
            WHERE i.type = 'new'
              AND ii.item_type = 'normal'
        SQL);
    }

    public function down(): void
    {
        // 语义归一不可逆（订正前 'normal' 与 'new' 无可靠区分标记），不做回滚。
    }
};
