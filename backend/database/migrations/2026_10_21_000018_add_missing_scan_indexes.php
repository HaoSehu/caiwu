<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 补三处高频扫描缺失的复合索引（数据库结构分析报告 P1-10）。
 *
 * 1. referral_rewards (status, available_at)：冻结奖励到期释放按状态+到期时间扫；
 * 2. coupons (status, expires_at)：过期券清理按状态+过期时间扫；
 * 3. users (referrer_user_id, referred_at)：下线列表按推荐人排序。
 *
 * 三者都是「等值列 + 范围/排序列」形态，复合索引顺序与查询谓词一致。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('referral_rewards', 'referral_rewards_status_available_idx', function (Blueprint $table): void {
            $table->index(['status', 'available_at'], 'referral_rewards_status_available_idx');
        });

        $this->addIndexIfMissing('coupons', 'coupons_status_expires_idx', function (Blueprint $table): void {
            $table->index(['status', 'expires_at'], 'coupons_status_expires_idx');
        });

        $this->addIndexIfMissing('users', 'users_referrer_referred_idx', function (Blueprint $table): void {
            $table->index(['referrer_user_id', 'referred_at'], 'users_referrer_referred_idx');
        });
    }

    public function down(): void
    {
        foreach ([
            'referral_rewards' => 'referral_rewards_status_available_idx',
            'coupons' => 'coupons_status_expires_idx',
            'users' => 'users_referrer_referred_idx',
        ] as $table => $index) {
            if (Schema::hasTable($table) && $this->indexExists($table, $index)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
            }
        }
    }

    private function addIndexIfMissing(string $table, string $index, callable $adder): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, $adder);
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'select count(*) as c from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
            [$table, $index],
        );

        return (int) ($rows[0]->c ?? 0) > 0;
    }
};
