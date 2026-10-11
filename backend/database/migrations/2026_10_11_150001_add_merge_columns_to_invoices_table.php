<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 账单合并（M1）：invoices 新增合并三列。
 *
 * - merged_into_id：子账单→所属总账单；NULL 即未被合并。FK ON DELETE SET NULL
 *   （总账单物理删除时子账单自动解除关联，保留自身数据回到独立待支付）。
 *   不复用 origin_invoice_id（续费语义，值域不可混用）。
 * - merge_role：区分合并总账单，避免扩展 type 枚举（全链路 type 白名单不改）。
 * - merged_at：合并时间。
 *
 * 可重入：列/索引/FK 均先探测再建，重复执行不报错；FK 单独一步创建
 * （列可能由历史半途迁移补齐，FK 已存在则跳过）。
 * 索引 (user_id,status,merged_into_id) 服务合并候选查询。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'merged_into_id')) {
                $table->unsignedBigInteger('merged_into_id')->nullable()->after('quantity')
                    ->comment('合并总账单ID（子账单→所属总账单；NULL=未被合并）');
            }
            if (! Schema::hasColumn('invoices', 'merge_role')) {
                $table->tinyInteger('merge_role')->default(0)->after('merged_into_id')
                    ->comment('合并角色：0普通 1合并总账单');
            }
            if (! Schema::hasColumn('invoices', 'merged_at')) {
                $table->dateTime('merged_at')->nullable()->after('merge_role')
                    ->comment('合并时间');
            }
        });

        // FK 与索引分步守护：先列后约束，半途重跑安全。
        Schema::table('invoices', function (Blueprint $table): void {
            if (! $this->hasMergedIntoForeignKey()) {
                $table->foreign('merged_into_id')
                    ->references('id')->on('invoices')
                    ->onDelete('set null');
            }
        });

        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'merged_into_id')) {
                return;
            }
            if (! $this->hasIndexByName('invoices', 'invoices_user_status_merged_idx')) {
                $table->index(['user_id', 'status', 'merged_into_id'], 'invoices_user_status_merged_idx');
            }
            if (! $this->hasIndexByName('invoices', 'invoices_merged_into_id_idx')) {
                $table->index('merged_into_id', 'invoices_merged_into_id_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            if ($this->hasIndexByName('invoices', 'invoices_user_status_merged_idx')) {
                $table->dropIndex('invoices_user_status_merged_idx');
            }
            if ($this->hasIndexByName('invoices', 'invoices_merged_into_id_idx')) {
                $table->dropIndex('invoices_merged_into_id_idx');
            }
            if ($this->hasMergedIntoForeignKey()) {
                $table->dropForeign('invoices_merged_into_id_foreign');
            }
            foreach (['merged_at', 'merge_role', 'merged_into_id'] as $column) {
                if (Schema::hasColumn('invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function hasMergedIntoForeignKey(): bool
    {
        foreach (Schema::getForeignKeys('invoices') as $foreignKey) {
            if (in_array('merged_into_id', (array) ($foreignKey['columns'] ?? []), true)) {
                return true;
            }
        }

        return false;
    }

    private function hasIndexByName(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }
};
