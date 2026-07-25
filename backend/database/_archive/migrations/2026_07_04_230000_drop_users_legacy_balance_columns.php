<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 删除 users 表中冗余的余额字段。
 *
 * 背景：这些字段已被 User Model 的 getBalanceAttribute 等 accessor
 * 代理到 user_accounts 表，users 表自身存储的值为冗余数据，
 * 统一移除以避免数据不一致。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drops = [
                'balance',
                'credit_limit',
                'referral_frozen_amount',
                'referral_available_amount',
                'referral_withdrawing_amount',
                'referral_withdrawn_amount',
            ];

            foreach ($drops as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    public function down(): void
    {
        // 按原始顺序重建余额列（均为 decimal(12,2) NOT NULL DEFAULT '0.00'）
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->default('0.00')->after('status');
            $table->decimal('credit_limit', 12, 2)->default('0.00')->after('balance');
            $table->decimal('referral_frozen_amount', 12, 2)->default('0.00')->after('credit_limit');
            $table->decimal('referral_available_amount', 12, 2)->default('0.00')->after('referral_frozen_amount');
            $table->decimal('referral_withdrawing_amount', 12, 2)->default('0.00')->after('referral_available_amount');
            $table->decimal('referral_withdrawn_amount', 12, 2)->default('0.00')->after('referral_withdrawing_amount');
        });
    }
};
