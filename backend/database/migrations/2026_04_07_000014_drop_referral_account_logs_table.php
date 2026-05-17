<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('referral_account_logs')) {
            Schema::drop('referral_account_logs');
        }
    }

    public function down(): void
    {
        // 当前重构为测试环境激进集中化，下行恢复不再提供 referral_account_logs 自动回建。
    }
};
