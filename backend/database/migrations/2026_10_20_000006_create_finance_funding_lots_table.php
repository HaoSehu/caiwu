<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 资金批次：每笔现金入账形成一个可核销的「钱从哪来」单元。
 *
 * 不变式：按账户 SUM(remaining_amount) == finance_accounts.available_amount。
 * - amount / remaining_amount 允许为负：仅用于 data_quality=calibration 的存量校准批次，
 *   表示「台账记录过但账户实际不存在」的资金；FIFO 只取 remaining_amount > 0。
 * - entry_id 与入账台账行 1:1；台账侧 funding_lot_id 建唯一索引形成双向可追溯。
 * - 批次创建后 amount 不再变更；remaining_amount 随消费/恢复单调调整。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_funding_lots')) {
            return;
        }

        Schema::create('finance_funding_lots', function (Blueprint $table): void {
            $table->id();
            $table->string('lot_no', 40)->comment('批次号：FL+时间+随机');
            $table->unsignedBigInteger('account_id')->comment('finance_accounts.id');
            $table->unsignedBigInteger('user_id')->comment('会员 users.id，冗余便于按用户回放');
            $table->unsignedBigInteger('entry_id')->comment('创建该批次的入账台账 finance_ledger_entries.id');
            $table->string('source_type', 30)->nullable()->comment('资金来源类型：payment/refund/manual_adjustment/referral_withdrawal/migration');
            $table->unsignedBigInteger('source_id')->nullable()->comment('资金来源单据主键');
            $table->string('origin_type', 30)->nullable()->comment('跨域溯源来源类型');
            $table->unsignedBigInteger('origin_id')->nullable()->comment('跨域溯源来源主键');
            $table->decimal('amount', 12, 2)->comment('批次原始入账金额，单位元；仅校准批次可为负');
            $table->decimal('remaining_amount', 12, 2)->default(0)->comment('剩余可核销金额，单位元；FIFO 只取大于 0');
            $table->string('data_quality', 24)->default('normal')->comment('数据质量：normal/replayed/calibration/refund');
            $table->dateTime('created_at')->nullable()->comment('批次创建时间，历史回放取原台账时间');

            $table->unique('lot_no', 'finance_funding_lots_lot_no_unique');
            $table->unique('entry_id', 'finance_funding_lots_entry_id_unique');
            $table->index(['account_id', 'id'], 'finance_funding_lots_account_fifo_index');
            $table->index(['account_id', 'remaining_amount'], 'finance_funding_lots_account_remaining_index');
            $table->index(['user_id', 'id'], 'finance_funding_lots_user_index');
            $table->index(['source_type', 'source_id'], 'finance_funding_lots_source_index');

            $table->foreign('account_id', 'finance_funding_lots_account_id_foreign')
                ->references('id')->on('finance_accounts')->onDelete('restrict');
            $table->foreign('user_id', 'finance_funding_lots_user_id_foreign')
                ->references('id')->on('users')->onDelete('restrict');
            $table->foreign('entry_id', 'finance_funding_lots_entry_id_foreign')
                ->references('id')->on('finance_ledger_entries')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_funding_lots');
    }
};
