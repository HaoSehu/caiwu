<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 提成计提记录：计提粒度=账单（invoice_id 唯一约束防重，重复回调幂等 skip）。
// 与推广大使返利并存双算（互不抵扣）；结算发放为线下发放的管理登记制，
// 全程不动用户余额、不写资金台账（AccountTransaction）。
// M1 合并账单场景以子账单计提，总账单本身不产生提成记录。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_commissions')) {
            return;
        }

        Schema::create('sales_commissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->nullable()->comment('受益销售 admin_users.id（管理员删除时置 NULL 保留财务留痕）');
            $table->unsignedBigInteger('user_id')->comment('客户 users.id');
            $table->unsignedBigInteger('order_id')->nullable()->comment('关联订单 orders.id，无订单账单为空');
            $table->unsignedBigInteger('invoice_id')->comment('账单 invoices.id，计提粒度=账单');
            $table->unsignedBigInteger('plan_id')->nullable()->comment('命中方案 sales_commission_plans.id，方案删除时置空');
            $table->json('plan_snapshot')->comment('方案与费率快照 {plan_id,plan_name,basis,rate,fixed_amount,tier_id,scene}');
            $table->decimal('base_amount', 12, 2)->default(0.00)->comment('计提基数（实付金额或订单金额）');
            $table->decimal('rate', 5, 2)->default(0.00)->comment('提成比例（%），固定金额场景为 0');
            $table->decimal('commission_amount', 12, 2)->default(0.00)->comment('提成金额');
            $table->tinyInteger('status')->default(0)->comment('0待结算 1已入批次 2已发放 3已取消');
            $table->unsignedBigInteger('settlement_id')->nullable()->comment('所属结算批次 sales_commission_settlements.id');
            $table->datetime('settled_at')->nullable()->comment('发放确认时间');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪 ID');
            $table->timestamps();

            $table->foreign('admin_user_id')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('sales_commission_plans')->nullOnDelete();
            // settlement_id 的 FK 由 2026_10_11_160007 建表后补挂（迁移序号保证先建 settlements）。

            $table->unique('invoice_id', 'sales_commissions_invoice_unique');
            $table->index(['admin_user_id', 'status'], 'sales_commissions_admin_status_idx');
            $table->index('user_id', 'sales_commissions_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commissions');
    }
};
