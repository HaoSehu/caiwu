<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 等级来源锁：auto=按销售额自动定级（原行为）；manual=管理员手工指定后锁定，
// 所有多动定级入口（返利结算/退款追回/绑定推荐关系/打开推广中心/区间重算）跳过 manual 用户，
// 管理端可通过恢复操作置回 auto 并立即按销售额重算。
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'member_level_source')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('member_level_source', 16)
                    ->default('auto')
                    ->after('member_level_id')
                    ->comment('auto=销售额自动定级 manual=管理员手工指定');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'member_level_source')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('member_level_source');
            });
        }
    }
};
