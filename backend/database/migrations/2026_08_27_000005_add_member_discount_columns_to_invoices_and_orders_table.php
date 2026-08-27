<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 账单与订单新增会员折扣层：member_discount_amount 为会员折扣减免金额（优惠券之前的一层），
// member_discount_snapshot 记录命中的 {等级ID, 营销组ID, 类型, 数值} 明细便于对账回溯。
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'orders'] as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'member_discount_amount')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->decimal('member_discount_amount', 12, 2)
                        ->default(0.00)
                        ->after('discount')
                        ->comment('会员等级×营销组折扣减免金额');
                    $table->json('member_discount_snapshot')
                        ->nullable()
                        ->after('coupon_snapshot')
                        ->comment('会员折扣命中快照 JSON');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'orders'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'member_discount_amount')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn(['member_discount_amount', 'member_discount_snapshot']);
                });
            }
        }
    }
};
