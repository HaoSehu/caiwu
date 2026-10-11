<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 内部销售提成：客户归属销售（管理员/员工）。
// NULL=未归属；销售账号删除时置回未归属（ON DELETE SET NULL）。
// 归属只影响归属后新支付的账单，不追溯历史。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'sales_owner_admin_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('sales_owner_admin_id')
                    ->nullable()
                    ->after('promotion_ambassador_id')
                    ->comment('内部销售归属（admin_users.id），空=未归属');
            });

            if (Schema::hasTable('admin_users')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->foreign('sales_owner_admin_id')
                        ->references('id')
                        ->on('admin_users')
                        ->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('users')
            && Schema::hasColumn('users', 'sales_owner_admin_id')
            && ! Schema::hasColumn('users', 'sales_owner_assigned_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->datetime('sales_owner_assigned_at')
                    ->nullable()
                    ->after('sales_owner_admin_id')
                    ->comment('归属设置时间');
            });
        }

        if (Schema::hasTable('users')
            && Schema::hasColumn('users', 'sales_owner_admin_id')
            && ! $this->hasSalesOwnerIndex()) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('sales_owner_admin_id', 'users_sales_owner_admin_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'sales_owner_admin_id')) {
            Schema::table('users', function (Blueprint $table): void {
                if ($this->hasSalesOwnerIndex()) {
                    $table->dropIndex('users_sales_owner_admin_id_index');
                }
                $table->dropForeign(['sales_owner_admin_id']);
                $table->dropColumn(['sales_owner_admin_id', 'sales_owner_assigned_at']);
            });
        }
    }

    private function hasSalesOwnerIndex(): bool
    {
        $indexes = Schema::getIndexes('users');

        return collect($indexes)->contains(
            fn (array $index): bool => in_array('sales_owner_admin_id', (array) ($index['columns'] ?? []), true)
        );
    }
};
