<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 台账 account_type 双命名归一：历史遗留 'cash_balance' 统一为 'cash'。
 *
 * 背景（M-7 / P1-9）：2026-03～06 旧系统导入段写入了 account_type='cash_balance'
 * 的台账行（实库 finance_ledger_entries / account_transactions 各 527 行），此后新增
 * 写入侧统一 'cash'，但从未有迁移归一。所有按 account_type='cash' 过滤的查询
 * （用户端收支明细、管理端台账、重建命令的现金聚合、一致性核对）都会漏掉这批
 * 存量行，造成用户看到的历史流水与账户余额对不上、重建无法自愈。
 *
 * 幂等：up 只更新仍为 'cash_balance' 的行，重复执行时已无命中。
 *
 * down 依赖 up 时建的回退记录表 finance_ledger_account_type_normalizations
 * 精确回退被本迁移改过的行：无法只凭 'cash' 反推原始命名（正常现金流水也是
 * 'cash'），不能把全部 'cash' 反改成 'cash_balance'。回退完成后记录表随之移除，
 * 与 up 的建表对称（再次 up 重新建表并重新登记）。
 *
 * account_transactions 若仍存在（T+7 归档前）同批处理；归档后
 * （改名为 archived_account_transactions）跳过，只处理新台账。
 */
return new class extends Migration
{
    private const NORMALIZATIONS_TABLE = 'finance_ledger_account_type_normalizations';

    public function up(): void
    {
        // 回退记录表无条件创建：保证各环境迁移后结构一致，不因「执行时是否恰有遗留行」漂移。
        $this->ensureNormalizationsTable();

        // 台账真源在前、旧表在后；归档环境旧表不存在时自然跳过。
        foreach (['finance_ledger_entries', 'account_transactions'] as $table) {
            $this->normalize($table);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::NORMALIZATIONS_TABLE)) {
            return;
        }

        foreach (['finance_ledger_entries', 'account_transactions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $restoreIds = DB::table(self::NORMALIZATIONS_TABLE)
                ->where('source_table', $table)
                ->orderBy('id')
                ->pluck('entry_id')
                ->map(static fn ($id): int => (int) $id)
                ->all();

            foreach (array_chunk($restoreIds, 500) as $chunk) {
                DB::table($table)
                    ->whereIn('id', $chunk)
                    ->where('account_type', 'cash')
                    ->update(['account_type' => 'cash_balance']);
            }
        }

        // 记录表只服务本迁移的回退，随 down 移除；再次 up 会重新建表并登记。
        Schema::dropIfExists(self::NORMALIZATIONS_TABLE);
    }

    private function normalize(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $ids = DB::table($table)
            ->where('account_type', 'cash_balance')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        $now = now()->toDateTimeString();
        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table(self::NORMALIZATIONS_TABLE)->insertOrIgnore(
                array_map(
                    static fn (int $id): array => [
                        'source_table' => $table,
                        'entry_id' => $id,
                        'created_at' => $now,
                    ],
                    $chunk,
                ),
            );
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            DB::table($table)
                ->whereIn('id', $chunk)
                ->where('account_type', 'cash_balance')
                ->update(['account_type' => 'cash']);
        }
    }

    private function ensureNormalizationsTable(): void
    {
        if (Schema::hasTable(self::NORMALIZATIONS_TABLE)) {
            return;
        }

        Schema::create(self::NORMALIZATIONS_TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('source_table', 64)->comment('被归一的台账表名：finance_ledger_entries / account_transactions');
            $table->unsignedBigInteger('entry_id')->comment('被归一行的主键，供归一迁移 down 精确回退');
            $table->dateTime('created_at')->nullable()->comment('归一执行时间');
            $table->unique(['source_table', 'entry_id'], self::NORMALIZATIONS_TABLE.'_source_entry_unique');
            // 中文表注释与全库口径一致（DatabaseTableCommentCoverageTest 会扫描全部基础表）。
            $table->comment('台账 account_type 归一记录表，仅用于归一迁移的精确回退');
        });
    }
};
