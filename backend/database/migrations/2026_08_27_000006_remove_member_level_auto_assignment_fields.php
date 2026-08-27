<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 等级完全手工化：移除自动定级依据字段（销售额区间、等级编码）与来源锁。
// 等级改为由管理员在用户详情页直接指定；分销返利、折扣矩阵与销量统计不受影响
// （users.total_sales_amount 与 user_referrals 保留用于返利与对账展示）。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_levels') && Schema::hasColumn('member_levels', 'code')) {
            Schema::table('member_levels', function (Blueprint $table): void {
                $table->dropUnique('member_levels_code_unique');
                $table->dropIndex('idx_member_level_sales_range');
                $table->dropColumn(['code', 'sales_amount_min', 'sales_amount_max']);
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'member_level_source')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('member_level_source');
            });
        }
    }

    public function down(): void
    {
        $restored = false;
        if (Schema::hasTable('member_levels') && ! Schema::hasColumn('member_levels', 'code')) {
            Schema::table('member_levels', function (Blueprint $table): void {
                // 不能带 unique 建列：历史行全为空串，两行以上即违反唯一约束令回滚失败
                $table->string('code', 30)->default('');
                $table->decimal('sales_amount_min', 12, 2)->default(0.00);
                $table->decimal('sales_amount_max', 12, 2)->nullable();
                $table->index(['sales_amount_min', 'sales_amount_max'], 'idx_member_level_sales_range');
            });
            $restored = true;
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'member_level_source')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('member_level_source', 16)->default('auto')->after('member_level_id');
            });
        }

        if ($restored) {
            echo "注意：member_levels.code 已恢复但历史行均为空串，请人工补值后再执行 ALTER TABLE member_levels ADD UNIQUE KEY member_levels_code_unique (code)。\n";
        }
    }
};
