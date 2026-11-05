<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * message_logs.delivery_key 加唯一索引。
 *
 * MessageDispatcher::writeAuditLog 的「先 UPDATE、未命中再 INSERT」在并发下会产生
 * 重复审计行：两个请求同时未命中 UPDATE，然后各自 INSERT。加唯一索引（channel, delivery_key）
 * 后由数据库拦下后到的一条，代码侧补一次 UPDATE（见 writeAuditLog 的 UniqueConstraintViolation
 * 分支），审计不重复。
 *
 * delivery_key 可空：MySQL 唯一索引允许多个 NULL，未带投递键的旧记录不受影响。
 * 先清理可能已存在的重复行（保留 id 最小的一条），再建索引。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_logs') || ! Schema::hasColumn('message_logs', 'delivery_key')) {
            return;
        }

        // 历史重复行清理：同 (channel, delivery_key) 只保留最早一条
        DB::statement(
            'DELETE l FROM message_logs l
             JOIN message_logs k
               ON k.channel = l.channel
              AND k.delivery_key = l.delivery_key
              AND k.id < l.id
             WHERE l.delivery_key IS NOT NULL AND l.delivery_key <> '."''".''
        );

        $existing = collect(DB::select('show index from message_logs'))->first(
            static fn ($index): bool => $index->Key_name === 'message_logs_channel_delivery_key_unique'
        );

        if ($existing === null) {
            Schema::table('message_logs', function (Blueprint $table): void {
                $table->unique(['channel', 'delivery_key'], 'message_logs_channel_delivery_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('message_logs')) {
            return;
        }

        $existing = collect(DB::select('show index from message_logs'))->first(
            static fn ($index): bool => $index->Key_name === 'message_logs_channel_delivery_key_unique'
        );

        if ($existing !== null) {
            Schema::table('message_logs', function (Blueprint $table): void {
                $table->dropUnique('message_logs_channel_delivery_key_unique');
            });
        }
    }
};
