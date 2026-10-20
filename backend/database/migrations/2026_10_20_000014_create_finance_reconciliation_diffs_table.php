<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 财务对账差异：持久化「无法自动核平」的存量问题，供人工跟进。
 *
 * 首批用途：迁移回放时某笔出账的批次余额不足以覆盖（历史虚高消费），
 * 已分配部分照常写入，缺口登记为 unallocated_debit，绝不伪造批次。
 * 唯一键保证同一对象同一检查项只留一条，重复运行用 upsert 幂等。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_reconciliation_diffs')) {
            return;
        }

        Schema::create('finance_reconciliation_diffs', function (Blueprint $table): void {
            $table->id();
            $table->string('check_type', 40)->comment('检查项：unallocated_debit/balance_mismatch/duplicate_receipt');
            $table->string('scope_type', 40)->comment('差异对象类型：ledger_entry/account/refund');
            $table->unsignedBigInteger('scope_id')->comment('差异对象主键');
            $table->decimal('expected_value', 12, 2)->nullable()->comment('应有值，单位元');
            $table->decimal('actual_value', 12, 2)->nullable()->comment('实际值，单位元');
            $table->decimal('diff_value', 12, 2)->nullable()->comment('差额，单位元；负数表示缺口');
            $table->json('details')->nullable()->comment('差异上下文快照');
            $table->string('status', 16)->default('open')->comment('状态：open/resolved/ignored');
            $table->dateTime('detected_at')->comment('首次发现时间');
            $table->dateTime('resolved_at')->nullable()->comment('处理完成时间');
            $table->timestamps();

            $table->unique(['check_type', 'scope_type', 'scope_id'], 'finance_recon_diffs_object_unique');
            $table->index(['status', 'detected_at'], 'finance_recon_diffs_status_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_reconciliation_diffs');
    }
};
