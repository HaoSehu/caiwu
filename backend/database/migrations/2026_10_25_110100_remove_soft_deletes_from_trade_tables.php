<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 交易核心四表统一删除语义（2026-10-25 评审裁决：先硬删）：
 * 摘除 services / invoices / orders / payments 的软删除列。
 *
 * - services：管理端删除改为硬删（HandlesAdminUserServices 事务内已显式清空
 *   order/ticket 关联并删除上游绑定），invoices/orders.service_id 由 SET NULL 兜底；
 * - invoices / orders / payments：删除入口为状态机终态，deleted_at 列无写入方；
 *   Payment 遵守「不物理删除」铁律，本迁移只移除休眠的软删列，不新增任何删除入口。
 *
 * down() 恢复列（可空 timestamp），存量行恢复后即保持 NULL。
 */
return new class extends Migration
{
    private const TABLES = ['services', 'invoices', 'orders', 'payments'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('deleted_at');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }
};
