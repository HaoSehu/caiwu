<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 用户与推广大使档位关联：NULL=未指派（返利按全局 referral.reward_rate 兜底），
// 大使删除时置回未指派，语义与 member_level_id 的外键一致。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'promotion_ambassador_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unsignedBigInteger('promotion_ambassador_id')
                    ->nullable()
                    ->after('member_level_id')
                    ->comment('推广大使档位，空=未指派');
            });

            if (Schema::hasTable('promotion_ambassadors')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->foreign('promotion_ambassador_id')
                        ->references('id')
                        ->on('promotion_ambassadors')
                        ->nullOnDelete();
                    $table->index('promotion_ambassador_id', 'users_promotion_ambassador_id_index');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'promotion_ambassador_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropForeign(['promotion_ambassador_id']);
                $table->dropIndex('users_promotion_ambassador_id_index');
                $table->dropColumn('promotion_ambassador_id');
            });
        }
    }
};
