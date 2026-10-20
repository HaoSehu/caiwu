<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 现金账户：接管 user_accounts.cash_balance 的余额真源职责。
 *
 * 与旧结构的关系：
 * - 旧 user_accounts 仍保留 credit_limit 与 4 个 referral_* 列，本表只承接现金余额。
 * - available_amount 允许为负：本地存量存在 1 个过程性负余额用户（user 117 / -30），
 *   迁移期不修正该值，待业务裁决；写入侧由服务层拒绝新的负余额扣款。
 * - version 用于乐观锁 CAS，与行锁构成双层并发保护。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_accounts')) {
            return;
        }

        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('会员 users.id');
            $table->char('currency', 3)->default('CNY')->comment('币种，当前仅 CNY');
            $table->decimal('available_amount', 12, 2)->default(0)->comment('可用余额，单位元；允许负值以保留历史异常');
            $table->decimal('frozen_amount', 12, 2)->default(0)->comment('冻结余额，单位元；提现阶段当前未启用');
            $table->unsignedInteger('version')->default(0)->comment('乐观锁版本号，每次余额变更 +1');
            $table->timestamps();

            $table->unique(['user_id', 'currency'], 'finance_accounts_user_currency_unique');
            $table->index('currency', 'finance_accounts_currency_index');
            $table->foreign('user_id', 'finance_accounts_user_id_foreign')
                ->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_accounts');
    }
};
