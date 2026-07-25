<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * H2 — 归档并删除 balance_logs 表
 *
 * BalanceLog Model 已从 app/Models/ 移除，
 * AccountTransaction 是唯一流水真源，业务层不再写 balance_logs，
 * 执行前先将历史数据归档到 account_transactions，再安全 DROP。
 */
return new class extends Migration
{
    /**
     * 归档数据后删除 balance_logs 表。
     */
    public function up(): void
    {
        // 步骤1：将 balance_logs 数据归档到 account_transactions
        // 只插入 account_transactions 中不存在对应 source_id 的记录，避免重复归档
        if (Schema::hasTable('balance_logs')) {
            DB::statement("
                INSERT INTO account_transactions
                    (user_id, account_type, event_type, change_amount, balance_after,
                     source_type, source_id, remark, created_at, updated_at)
                SELECT
                    bl.user_id,
                    'cash',
                    bl.event_type,
                    bl.change_amount,
                    bl.balance_after,
                    'balance_log_archive',
                    bl.id,
                    bl.remark,
                    bl.created_at,
                    bl.created_at
                FROM balance_logs bl
                WHERE NOT EXISTS (
                    SELECT 1 FROM account_transactions at2
                    WHERE at2.source_type = 'balance_log_archive'
                      AND at2.source_id = bl.id
                )
            ");
        }

        // 步骤2：删除 balance_logs 表
        Schema::dropIfExists('balance_logs');
    }

    /**
     * 回滚：重建 balance_logs 表结构（与原始结构一致）。
     * 注意：归档到 account_transactions 的数据不会回写，
     * source_type = 'balance_log_archive' 的记录可手动核查。
     */
    public function down(): void
    {
        if (Schema::hasTable('balance_logs')) {
            return;
        }

        Schema::create('balance_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // 用户 ID，不加外键约束以保持与原结构一致
            $table->unsignedBigInteger('user_id')->comment('用户ID');

            // 事件类型：充值 / 消费 / 退款 / 调整
            $table->string('event_type', 20)->comment('事件类型: recharge|consume|refund|adjust');

            // 变动金额（正负均可）与变动后余额
            $table->decimal('change_amount', 12, 2)->comment('变动金额');
            $table->decimal('balance_after', 12, 2)->comment('变动后余额');

            // 备注与关联业务 ID
            $table->string('remark', 200)->default('')->comment('备注');
            $table->unsignedBigInteger('reference_id')->nullable()->comment('关联业务ID');

            // 仅记录创建时间，与原表保持一致
            $table->timestamp('created_at')
                ->default(DB::raw('CURRENT_TIMESTAMP'))
                ->comment('创建时间');

            // 索引
            $table->index('event_type', 'balance_logs_type_index');
            $table->index(['user_id', 'created_at'], 'balance_logs_user_created_at_idx');
            $table->index(['user_id', 'event_type', 'created_at'], 'balance_logs_user_type_created_at_idx');
        });
    }
};
