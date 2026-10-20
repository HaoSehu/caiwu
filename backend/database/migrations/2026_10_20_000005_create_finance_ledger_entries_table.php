<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 不可变资金台账：接管 account_transactions 的职责。
 *
 * 设计要点：
 * - 保留 account_type（cash / referral_frozen / referral_available /
 *   referral_pending_withdrawal / referral_withdrawn）。旧表混存返利四类账户，
 *   按「仅现金」重建会丢失 LedgerConsistencyCheck 与前端下发的历史行。
 * - 主键在迁移期按原 id 复制，供 recharge_records 两条外键平滑重指。
 * - entry_no 为新增序号：历史行由迁移命令生成 LG{id}，新行使用 FL 前缀。
 * - 表只插不改：不设 updated_at，服务层不提供更新入口。
 * - funding_lot_id 与 finance_funding_lots.entry_id 构成 1:1，但方向相反，
 *   因此仅建唯一索引不建外键，避免循环依赖。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_ledger_entries')) {
            return;
        }

        Schema::create('finance_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_no', 40)->comment('流水序号：历史行 LG{id}，新行 FL+时间+随机');
            $table->unsignedBigInteger('account_id')->nullable()->comment('finance_accounts.id；返利等非现金子账户为空');
            $table->unsignedBigInteger('user_id')->comment('会员 users.id，冗余便于按用户查询');
            $table->string('account_type', 30)->default('cash')->comment('账户类型：cash=现金，referral_*=返利子账户');
            $table->string('event_type', 30)->comment('资金事件类型，见 FinanceLedgerEventType');
            $table->decimal('change_amount', 12, 2)->comment('变动金额，单位元；收入为正、支出为负');
            $table->decimal('balance_after', 12, 2)->comment('变动后可用余额快照，单位元');
            $table->char('currency', 3)->default('CNY')->comment('币种，当前仅 CNY');
            $table->unsignedBigInteger('funding_lot_id')->nullable()->comment('入账行对应的资金批次 finance_funding_lots.id');
            $table->string('source_type', 30)->nullable()->comment('业务来源类型：invoice/payment/manual_adjustment/referral_withdrawal/verification');
            $table->unsignedBigInteger('source_id')->nullable()->comment('业务来源单据主键');
            $table->string('origin_type', 30)->nullable()->comment('跨域溯源来源类型');
            $table->unsignedBigInteger('origin_id')->nullable()->comment('跨域溯源来源主键');
            $table->string('operator_type', 30)->nullable()->comment('操作者类型：admin/system/member/legacy');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作者主键；legacy 历史行为空');
            $table->string('operator_name', 50)->nullable()->comment('操作者名称快照');
            $table->string('remark', 255)->nullable()->comment('备注，展示用');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪号');
            $table->dateTime('created_at')->nullable()->comment('发生时间；历史行沿用原时间');

            $table->unique('entry_no', 'finance_ledger_entries_entry_no_unique');
            $table->unique('funding_lot_id', 'finance_ledger_entries_funding_lot_unique');
            $table->index(['user_id', 'account_type', 'created_at'], 'finance_ledger_entries_user_type_time_index');
            $table->index(['user_id', 'event_type', 'created_at'], 'finance_ledger_entries_user_event_time_index');
            $table->index(['source_type', 'source_id'], 'finance_ledger_entries_source_index');
            $table->index(['origin_type', 'origin_id'], 'finance_ledger_entries_origin_index');
            $table->index('trace_id', 'finance_ledger_entries_trace_index');
            $table->index('created_at', 'finance_ledger_entries_created_at_index');

            $table->foreign('account_id', 'finance_ledger_entries_account_id_foreign')
                ->references('id')->on('finance_accounts')->onDelete('restrict');
            $table->foreign('user_id', 'finance_ledger_entries_user_id_foreign')
                ->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_ledger_entries');
    }
};
