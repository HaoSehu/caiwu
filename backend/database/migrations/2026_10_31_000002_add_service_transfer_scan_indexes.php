<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 实例转移闸门的账单扫描索引（第三轮代码审查 P1）。
 *
 * 转移前与锁内都要按「实例 + 待支付」和「实例 + 已付未履约」各查一次账单
 * （见 HandlesAdminUserServices::assertServiceTransferable()）：
 *   where service_id = ? and status = ? [and fulfillment_pending = 1] order by id desc
 * 现有 invoices_service_id_idx 是单列索引，取到 service_id 后仍要回表逐行过滤
 * status 再对 id 做 filesort。复合索引把等值列前置、id 由主键兜底，两次查询
 * 都能直接定位到目标行。
 *
 * 只新增索引，不改写入侧；按「迁移只新增」约定提供对称的 down()。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices') || $this->indexExists('invoices', 'invoices_service_status_id_idx')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index(['service_id', 'status', 'id'], 'invoices_service_status_id_idx');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && $this->indexExists('invoices', 'invoices_service_status_id_idx')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropIndex('invoices_service_status_id_idx');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'select count(*) as c from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
            [$table, $index],
        );

        return (int) ($rows[0]->c ?? 0) > 0;
    }
};
