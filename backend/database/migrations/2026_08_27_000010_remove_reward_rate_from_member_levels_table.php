<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 返利比例从会员等级剥离：等级只承载身份与折扣矩阵权益，
// 邀请返利比例改由 users.promotion_ambassador_id 指向的推广大使档位决定；
// referral_rewards.reward_rate 为历史快照字段，不受影响。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_levels') && Schema::hasColumn('member_levels', 'reward_rate')) {
            Schema::table('member_levels', function (Blueprint $table): void {
                $table->dropColumn('reward_rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('member_levels') && ! Schema::hasColumn('member_levels', 'reward_rate')) {
            Schema::table('member_levels', function (Blueprint $table): void {
                $table->decimal('reward_rate', 5, 2)->default(0.00)->comment('返利比例（%）');
            });
        }
    }
};
