<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M12 客户关怀：users 新增 birth_date（功能模块扩展 2026-10-11）。
 *
 * 生日关怀按月日匹配 + offset 计算；是否从实名身份证号派生生日默认关闭
 * （隐私敏感，仅明示授权后可选项）。幂等 hasColumn 守卫。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'birth_date')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->date('birth_date')->nullable()->after('phone')
                    ->comment('出生日期（生日关怀用；未填则生日计划跳过该用户）');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'birth_date')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('birth_date');
            });
        }
    }
};
