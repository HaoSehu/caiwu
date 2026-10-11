<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 账单合并（M1）：子账单关联快照表。
 *
 * - child_invoice_id 唯一约束：一张子账单同一时刻只能挂在一张总账单下，
 *   防重复挂载（并发合并的第二道防线，服务层行锁复核之外兜底）。
 * - amount_snapshot：合并时子账单金额快照（拆分核对用，不随子账单后续变化漂移）。
 * - child_invoice_no：子账单号快照，子账单物理删除后仍可追溯。
 * - 不带 updated_at：快照行只插不改，删除即解除关联。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_merge_items')) {
            return;
        }

        Schema::create('invoice_merge_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('master_invoice_id')->comment('总账单ID（关联 invoices.id）');
            $table->unsignedBigInteger('child_invoice_id')->unique()->comment('子账单ID（唯一，防重复挂载；关联 invoices.id）');
            $table->string('child_invoice_no', 64)->comment('子账单号快照');
            $table->decimal('amount_snapshot', 12, 2)->default(0)->comment('合并时子账单金额快照（拆分核对用）');
            $table->timestamp('created_at')->nullable();

            $table->index('master_invoice_id', 'invoice_merge_items_master_idx');
            $table->foreign('master_invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('child_invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_merge_items');
    }
};
