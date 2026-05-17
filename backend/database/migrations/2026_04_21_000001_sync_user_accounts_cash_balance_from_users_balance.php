<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_accounts')) {
            return;
        }

        DB::statement('
            UPDATE user_accounts ua
            JOIN users u ON u.id = ua.user_id
            SET ua.cash_balance = u.balance
            WHERE u.deleted_at IS NULL
              AND u.balance != ua.cash_balance
        ');
    }

    public function down(): void
    {
        // 数据对齐操作不可逆，down 为空
    }
};
