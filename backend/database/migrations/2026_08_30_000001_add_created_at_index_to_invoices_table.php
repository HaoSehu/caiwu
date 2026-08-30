<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// invoices 补 created_at 索引（与 orders_created_at_idx 对称）：
// 管理端账单列表/台账汇总按 created_at BETWEEN 过滤且无 status 条件时，
// 此前退化为全表扫描 + filesort。命名沿用 *_created_at_idx 口径。
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        if ($this->indexExists('invoices', 'invoices_created_at_idx')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index('created_at', 'invoices_created_at_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoices')) {
            return;
        }

        if (! $this->indexExists('invoices', 'invoices_created_at_idx')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_created_at_idx');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? '') === $indexName) {
                return true;
            }
        }

        return false;
    }
};
