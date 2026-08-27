<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 推广大使档位拆分「新购返利」（reward_rate）与「续费返利」（renewal_reward_rate）：
// 新购订单按 reward_rate，续费订单按 renewal_reward_rate，未指派均回退全局 referral 配置。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotion_ambassadors') && ! Schema::hasColumn('promotion_ambassadors', 'renewal_reward_rate')) {
            Schema::table('promotion_ambassadors', function (Blueprint $table): void {
                $table->decimal('renewal_reward_rate', 5, 2)->default(0.00)->comment('续费返利比例（%）0-100');
            });
        }

        // 续费返利为新功能，默认大使续费比例保持 0.00，不产生资金变化，由运营配置后生效
        if (Schema::hasTable('promotion_ambassadors')) {
            DB::table('promotion_ambassadors')->where('name', '默认大使')->update(['renewal_reward_rate' => 0.00]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('promotion_ambassadors') && Schema::hasColumn('promotion_ambassadors', 'renewal_reward_rate')) {
            Schema::table('promotion_ambassadors', function (Blueprint $table): void {
                $table->dropColumn('renewal_reward_rate');
            });
        }
    }
};
