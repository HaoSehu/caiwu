<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 消费映射：一笔现金出账按 FIFO 拆分到若干个资金批次。
 *
 * 两个「减少」必须分开，语义不同：
 * - restored_amount：预扣释放、支付取消等「这笔钱又回来了」，批次 remaining 真恢复；
 * - refunded_amount：账单退款追认，只登记「这笔消费被退款覆盖」，
 *   原批次不恢复（退款款以新的退款批次进入账户）。
 *
 * (entry_id, lot_id) 唯一：同一笔出账对同一批次只允许一条映射。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_consumptions')) {
            return;
        }

        Schema::create('finance_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('account_id')->comment('finance_accounts.id');
            $table->unsignedBigInteger('user_id')->comment('会员 users.id');
            $table->unsignedBigInteger('lot_id')->comment('被消费的资金批次 finance_funding_lots.id');
            $table->unsignedBigInteger('entry_id')->comment('出账台账 finance_ledger_entries.id');
            $table->string('source_type', 30)->nullable()->comment('消费来源类型：invoice/manual_adjustment/verification/referral_withdrawal');
            $table->unsignedBigInteger('source_id')->nullable()->comment('消费来源单据主键');
            $table->decimal('amount', 12, 2)->comment('从该批次扣除的金额，单位元');
            $table->decimal('restored_amount', 12, 2)->default(0)->comment('已恢复金额，单位元；恢复时批次 remaining 同步回补');
            $table->decimal('refunded_amount', 12, 2)->default(0)->comment('已退款追认金额，单位元；仅登记，不回补批次');
            $table->unsignedBigInteger('restored_entry_id')->nullable()->comment('最近一次恢复对应的台账行');
            $table->dateTime('created_at')->nullable()->comment('创建时间');

            $table->unique(['entry_id', 'lot_id'], 'finance_consumptions_entry_lot_unique');
            $table->index(['lot_id', 'id'], 'finance_consumptions_lot_index');
            $table->index(['source_type', 'source_id'], 'finance_consumptions_source_index');
            $table->index(['user_id', 'id'], 'finance_consumptions_user_index');

            $table->foreign('account_id', 'finance_consumptions_account_id_foreign')
                ->references('id')->on('finance_accounts')->onDelete('restrict');
            $table->foreign('lot_id', 'finance_consumptions_lot_id_foreign')
                ->references('id')->on('finance_funding_lots')->onDelete('restrict');
            $table->foreign('entry_id', 'finance_consumptions_entry_id_foreign')
                ->references('id')->on('finance_ledger_entries')->onDelete('restrict');
            $table->foreign('restored_entry_id', 'finance_consumptions_restored_entry_id_foreign')
                ->references('id')->on('finance_ledger_entries')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_consumptions');
    }
};
