<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * message_delivery_attempts 补 message_logs 外键（数据库结构分析报告 P1-15）。
 *
 * 原表只有普通索引没有外键：message_logs 被归档清理后会留下指向已删主表的孤儿尝试行，
 * 而且没有任何级联约束保护。FOREIGN KEY ... ON DELETE CASCADE 让投递尝试始终跟随
 * 逻辑消息生命周期，与「归档先删子表再删主表」的顺序一致。
 *
 * 上线前先清历史孤儿行，否则加外键会因数据不满足约束而失败。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('message_logs') || ! Schema::hasTable('message_delivery_attempts')) {
            return;
        }

        if ($this->foreignKeyExists()) {
            return;
        }

        // 历史孤儿：主表已清但尝试行残留，先删掉（这些行已无任何可追溯的主记录）。
        $orphans = DB::table('message_delivery_attempts')
            ->whereRaw('NOT EXISTS (SELECT 1 FROM message_logs m WHERE m.id = message_delivery_attempts.message_log_id)')
            ->count();

        if ($orphans > 0) {
            DB::table('message_delivery_attempts')
                ->whereRaw('NOT EXISTS (SELECT 1 FROM message_logs m WHERE m.id = message_delivery_attempts.message_log_id)')
                ->delete();
        }

        Schema::table('message_delivery_attempts', function (Blueprint $table): void {
            $table->foreign('message_log_id', 'message_delivery_attempts_message_log_id_fk')
                ->references('id')->on('message_logs')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('message_delivery_attempts')) {
            return;
        }

        if ($this->foreignKeyExists()) {
            Schema::table('message_delivery_attempts', function (Blueprint $table): void {
                $table->dropForeign('message_delivery_attempts_message_log_id_fk');
            });
        }
    }

    private function foreignKeyExists(): bool
    {
        $rows = DB::select(
            "select count(*) as c from information_schema.table_constraints
             where table_schema = database() and table_name = 'message_delivery_attempts'
             and constraint_name = 'message_delivery_attempts_message_log_id_fk'",
        );

        return (int) ($rows[0]->c ?? 0) > 0;
    }
};
