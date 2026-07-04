<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 将 invoices.due_date 改为可为 NULL。
     *
     * 充值（recharge）、扣款（deduction）、推广返利（referral_credit）类型的账单
     * 在创建时即为已付款状态，不存在"截止日期"概念；
     * 原先写 due_date=now() 是为了满足 NOT NULL 约束，语义上有误导性。
     * 此迁移使到期日仅在需要它的账单类型（normal/new/renew/upgrade）上有意义。
     */
    public function up(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'due_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->date('due_date')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // 回滚：将 NULL 值置为账单创建日，再改回 NOT NULL
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE invoices SET due_date = DATE(created_at) WHERE due_date IS NULL"
        );

        if (Schema::hasColumn('invoices', 'due_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->date('due_date')->nullable(false)->change();
            });
        }
    }
};
