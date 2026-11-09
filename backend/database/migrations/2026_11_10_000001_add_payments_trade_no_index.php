<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payments.trade_no 补普通索引。
 *
 * schema dump 里已有 payments_trade_no_index，但历史迁移从未创建它，
 * 存量库不会随 dump 更新获得索引；补录交易号全局查重、按网关流水号对账
 * 都按 trade_no 过滤，缺索引会随支付单增长退化为全表扫描。
 *
 * 非唯一索引：历史数据允许 NULL（未支付/待回调），唯一性由业务查重与状态机保证。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if ($this->indexExists('payments', 'payments_trade_no_index')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->index('trade_no', 'payments_trade_no_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        if (! $this->indexExists('payments', 'payments_trade_no_index')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_trade_no_index');
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
