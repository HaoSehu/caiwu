<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 等级表格进一步精简：移除排序值字段（展示与查询按 id 顺序）。
// 营销产品组（marketing_product_groups.sort_order）不受影响。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_levels') && Schema::hasColumn('member_levels', 'sort_order')) {
            Schema::table('member_levels', function (Blueprint $table): void {
                $table->dropIndex('idx_member_level_status_sort');
                $table->dropColumn('sort_order');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('member_levels') && ! Schema::hasColumn('member_levels', 'sort_order')) {
            Schema::table('member_levels', function (Blueprint $table): void {
                $table->integer('sort_order')->default(0);
                $table->index(['status', 'sort_order'], 'idx_member_level_status_sort');
            });
        }
    }
};
