<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 退款批次证据：回答「这笔退款退的是哪一批钱」。
 *
 * allocation_type 两种取值：
 * - new_lot：退款款进入账户时新建的批次（真实退款必有且仅有一条）；
 * - consumption_mark：追认该退款覆盖了哪几笔原消费（余额支付订单才产生）。
 *
 * 使用 allocation_key 唯一键而非组合唯一：MySQL 唯一索引对 NULL 视为互不相等，
 * 组合键无法约束「同一退款同一批次只登记一次」。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_refund_allocations')) {
            return;
        }

        Schema::create('finance_refund_allocations', function (Blueprint $table): void {
            $table->id();
            $table->string('allocation_key', 160)->comment('幂等键：refund_id:type:lot_id:consumption_id');
            $table->unsignedBigInteger('refund_id')->comment('finance_refunds.id');
            $table->string('allocation_type', 24)->comment('分配类型：new_lot=退款新批次，consumption_mark=追认原消费');
            $table->unsignedBigInteger('lot_id')->nullable()->comment('关联资金批次 finance_funding_lots.id');
            $table->unsignedBigInteger('consumption_id')->nullable()->comment('关联消费映射 finance_consumptions.id');
            $table->decimal('amount', 12, 2)->comment('分配金额，单位元');
            $table->dateTime('created_at')->nullable()->comment('创建时间');

            $table->unique('allocation_key', 'finance_refund_allocations_key_unique');
            $table->index('refund_id', 'finance_refund_allocations_refund_index');
            $table->index('lot_id', 'finance_refund_allocations_lot_index');
            $table->index('consumption_id', 'finance_refund_allocations_consumption_index');

            $table->foreign('refund_id', 'finance_refund_allocations_refund_id_foreign')
                ->references('id')->on('finance_refunds')->onDelete('restrict');
            $table->foreign('lot_id', 'finance_refund_allocations_lot_id_foreign')
                ->references('id')->on('finance_funding_lots')->onDelete('set null');
            $table->foreign('consumption_id', 'finance_refund_allocations_consumption_id_foreign')
                ->references('id')->on('finance_consumptions')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_refund_allocations');
    }
};
