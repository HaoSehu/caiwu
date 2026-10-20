<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 退款单：接管 refunds 的职责，并移除双通道模型。
 *
 * 通道简化（2026-10-20 用户裁决）：
 * - 删除 refund_method，只保留「退款到余额」一条通道；
 * - 历史 manual_mark 行以 funds_moved=0 + data_origin=legacy_manual_mark 只读保留，
 *   仅用于事实追溯，不再产生新行，也不参与可退额计算。
 *
 * 主键在迁移期按原 id 复制，供 recharge_records.refund_id 外键平滑重指。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_refunds')) {
            return;
        }

        Schema::create('finance_refunds', function (Blueprint $table): void {
            $table->id();
            $table->string('refund_no', 40)->comment('退款单号：RF+时间+随机');
            $table->unsignedBigInteger('user_id')->comment('会员 users.id');
            $table->unsignedBigInteger('invoice_id')->comment('被退款账单 invoices.id');
            $table->unsignedBigInteger('refund_invoice_id')->nullable()->comment('退款生成的红字账单 invoices.id');
            $table->unsignedBigInteger('payment_id')->nullable()->comment('原支付单 payments.id；余额支付为空');
            $table->decimal('amount', 12, 2)->comment('退款金额，单位元');
            $table->char('currency', 3)->default('CNY')->comment('币种，当前仅 CNY');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态：0=处理中，1=已完成，2=失败');
            $table->unsignedTinyInteger('funds_moved')->default(1)->comment('是否真实动账：1=已退回余额，0=历史纯标记行');
            $table->string('data_origin', 24)->default('native')->comment('来源：native/legacy_balance/legacy_manual_mark');
            $table->string('reason', 255)->default('')->comment('退款原因');
            $table->json('delivery_evidence')->nullable()->comment('交付状态核查证据快照，仅余额退款写入');
            $table->string('operator_type', 30)->nullable()->comment('操作者类型：admin/system/member/legacy');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作者主键');
            $table->string('operator_name', 50)->nullable()->comment('操作者名称快照');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪号');
            $table->dateTime('refunded_at')->nullable()->comment('退款完成时间');
            $table->timestamps();

            $table->unique('refund_no', 'finance_refunds_refund_no_unique');
            $table->index(['invoice_id', 'status', 'id'], 'finance_refunds_invoice_status_index');
            $table->index(['user_id', 'created_at'], 'finance_refunds_user_time_index');
            $table->index('trace_id', 'finance_refunds_trace_index');

            $table->foreign('user_id', 'finance_refunds_user_id_foreign')
                ->references('id')->on('users')->onDelete('restrict');
            $table->foreign('invoice_id', 'finance_refunds_invoice_id_foreign')
                ->references('id')->on('invoices')->onDelete('restrict');
            $table->foreign('refund_invoice_id', 'finance_refunds_refund_invoice_id_foreign')
                ->references('id')->on('invoices')->onDelete('set null');
            $table->foreign('payment_id', 'finance_refunds_payment_id_foreign')
                ->references('id')->on('payments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_refunds');
    }
};
