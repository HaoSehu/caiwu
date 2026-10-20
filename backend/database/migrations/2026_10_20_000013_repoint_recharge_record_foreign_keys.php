<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 把 recharge_records 的两条资金外键从旧表重指到新表。
 *
 * 为什么必须单独一步：旧表改名归档后，MySQL 会把子表外键一并指向改名后的表，
 * 新代码写入 recharge_records 时会指向 legacy 表而失败。因此必须在归档前显式重指。
 *
 * 安全闸：重指前断言两条引用列的取值在新表中 100% 存在。若不满足（典型场景：
 * 先 migrate 后 rebuild），直接抛错中止，避免建立指向空表的错误外键。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recharge_records')
            || ! Schema::hasTable('finance_ledger_entries')
            || ! Schema::hasTable('finance_refunds')) {
            return;
        }

        $this->assertReferencesResolvable();

        if ($this->hasForeignKey('recharge_records', 'recharge_records_account_transaction_id_foreign')) {
            Schema::table('recharge_records', function (Blueprint $table): void {
                $table->dropForeign('recharge_records_account_transaction_id_foreign');
            });
        }
        if ($this->hasForeignKey('recharge_records', 'recharge_records_refund_id_foreign')) {
            Schema::table('recharge_records', function (Blueprint $table): void {
                $table->dropForeign('recharge_records_refund_id_foreign');
            });
        }

        if (! $this->hasForeignKey('recharge_records', 'recharge_records_account_transaction_id_foreign')) {
            Schema::table('recharge_records', function (Blueprint $table): void {
                $table->foreign('account_transaction_id', 'recharge_records_account_transaction_id_foreign')
                    ->references('id')->on('finance_ledger_entries')->onDelete('set null');
            });
        }
        if (! $this->hasForeignKey('recharge_records', 'recharge_records_refund_id_foreign')) {
            Schema::table('recharge_records', function (Blueprint $table): void {
                $table->foreign('refund_id', 'recharge_records_refund_id_foreign')
                    ->references('id')->on('finance_refunds')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('recharge_records') || ! Schema::hasTable('account_transactions')) {
            return;
        }

        foreach (['recharge_records_account_transaction_id_foreign', 'recharge_records_refund_id_foreign'] as $name) {
            if ($this->hasForeignKey('recharge_records', $name)) {
                Schema::table('recharge_records', function (Blueprint $table) use ($name): void {
                    $table->dropForeign($name);
                });
            }
        }

        Schema::table('recharge_records', function (Blueprint $table): void {
            $table->foreign('account_transaction_id', 'recharge_records_account_transaction_id_foreign')
                ->references('id')->on('account_transactions')->onDelete('set null');
            $table->foreign('refund_id', 'recharge_records_refund_id_foreign')
                ->references('id')->on('refunds')->onDelete('set null');
        });
    }

    private function assertReferencesResolvable(): void
    {
        $missingLedger = DB::table('recharge_records')
            ->whereNotNull('account_transaction_id')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM finance_ledger_entries e WHERE e.id = recharge_records.account_transaction_id)')
            ->count();
        if ($missingLedger > 0) {
            throw new RuntimeException(
                "recharge_records.account_transaction_id 有 {$missingLedger} 行在新台账中不存在，请先执行 php artisan finance:rebuild --execute"
            );
        }

        $missingRefund = DB::table('recharge_records')
            ->whereNotNull('refund_id')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM finance_refunds r WHERE r.id = recharge_records.refund_id)')
            ->count();
        if ($missingRefund > 0) {
            throw new RuntimeException(
                "recharge_records.refund_id 有 {$missingRefund} 行在新退款表中不存在，请先执行 php artisan finance:rebuild --execute"
            );
        }
    }

    private function hasForeignKey(string $table, string $constraint): bool
    {
        $database = DB::connection()->getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
