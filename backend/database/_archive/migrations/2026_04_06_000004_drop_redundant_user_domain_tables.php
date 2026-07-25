<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_profiles')) {
            Schema::drop('user_profiles');
        }

        if (Schema::hasTable('user_verifications')) {
            Schema::drop('user_verifications');
        }

        if (Schema::hasTable('user_referrals')) {
            Schema::drop('user_referrals');
        }
    }

    public function down(): void
    {
        // 当前重构为测试环境激进集中化，下行恢复不再提供旧子表结构自动回建。
    }
};
