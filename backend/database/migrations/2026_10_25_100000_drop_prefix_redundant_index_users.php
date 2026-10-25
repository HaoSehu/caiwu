<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 删除 users 表的前缀冗余索引 users_referrer_user_id_index。
 *
 * 该索引的查询路径已被 users_referrer_referred_idx（referrer_user_id + referred_at）
 * 的最左前缀完全覆盖（两者均非唯一，2026-10-10 前缀冗余清理批次的漏网项）。
 * 全仓无 forceIndex/useIndex 引用，可安全删除；down() 原样重建。
 */
return new class extends Migration
{
    private const INDEX = 'users_referrer_user_id_index';

    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasIndex('users', self::INDEX)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasIndex('users', self::INDEX)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('referrer_user_id', self::INDEX);
            });
        }
    }
};
