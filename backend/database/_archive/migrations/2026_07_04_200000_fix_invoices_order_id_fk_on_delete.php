<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H4 — 修复 invoices.order_id 外键的 ON DELETE 行为
 *
 * 原外键 fk_invoices_order_id 使用 RESTRICT，
 * orders 软删除后无法物理清理父行。
 * 改为 SET NULL，允许 orders 被物理删除后 invoice 保留（order_id 置空）。
 */
return new class extends Migration
{
    /**
     * 将外键 ON DELETE 行为从 RESTRICT 改为 SET NULL。
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 先检查外键是否存在，存在则删除，避免重复操作报错
            $foreignKeys = $this->getTableForeignKeys('invoices');
            if (in_array('fk_invoices_order_id', $foreignKeys)) {
                $table->dropForeign('fk_invoices_order_id');
            }

            // 添加新外键：ON DELETE SET NULL
            $table->foreign('order_id', 'fk_invoices_order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('set null');
        });
    }

    /**
     * 回滚：恢复原 RESTRICT 外键。
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // 删除 SET NULL 外键
            $foreignKeys = $this->getTableForeignKeys('invoices');
            if (in_array('fk_invoices_order_id', $foreignKeys)) {
                $table->dropForeign('fk_invoices_order_id');
            }

            // 恢复原 RESTRICT 外键（Laravel 默认行为即为 RESTRICT）
            $table->foreign('order_id', 'fk_invoices_order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('restrict');
        });
    }

    /**
     * 获取指定表的所有外键名称列表。
     *
     * @return array<string>
     */
    private function getTableForeignKeys(string $tableName): array
    {
        $dbName = config('database.connections.mysql.database');
        $rows = DB::select("
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA    = ?
              AND TABLE_NAME      = ?
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ", [$dbName, $tableName]);

        return array_column($rows, 'CONSTRAINT_NAME');
    }
};
