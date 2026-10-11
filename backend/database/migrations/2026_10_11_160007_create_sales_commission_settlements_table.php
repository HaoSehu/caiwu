<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 结算批次：线下发放的管理登记制（与退款标记「纯登记不动钱」哲学一致），
// 不入用户余额、不写资金台账。发放确认=登记事实，无资金动作。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_commission_settlements')) {
            return;
        }

        Schema::create('sales_commission_settlements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_user_id')->nullable()->comment('受益销售 admin_users.id（管理员删除时置 NULL 保留财务留痕）');
            $table->date('period_start')->comment('结算期间起');
            $table->date('period_end')->comment('结算期间止');
            $table->decimal('total_amount', 12, 2)->default(0.00)->comment('批次提成总额');
            $table->integer('commission_count')->default(0)->comment('批次计提笔数');
            $table->tinyInteger('status')->default(0)->comment('0待审核 1已发放 2已驳回');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作管理员 admin_users.id');
            $table->datetime('settled_at')->nullable()->comment('发放确认时间');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();

            $table->foreign('admin_user_id')->references('id')->on('admin_users')->nullOnDelete();
            $table->index(['admin_user_id', 'status'], 'sales_commission_settlements_admin_status_idx');
        });

        // 补挂 sales_commissions.settlement_id → sales_commission_settlements 的 FK
        // （160006 建表时本表尚未存在，按序号在此收口；重复执行由 hasColumn + FK 存在性守卫保证可重入）。
        if (Schema::hasTable('sales_commissions')
            && Schema::hasColumn('sales_commissions', 'settlement_id')
            && ! $this->hasSettlementForeignKey()) {
            Schema::table('sales_commissions', function (Blueprint $table): void {
                $table->foreign('settlement_id')
                    ->references('id')
                    ->on('sales_commission_settlements')
                    ->nullOnDelete();
            });
        }
    }

    private function hasSettlementForeignKey(): bool
    {
        $foreignKeys = Schema::getForeignKeys('sales_commissions');

        return collect($foreignKeys)->contains(
            fn (array $foreignKey): bool => in_array('settlement_id', (array) ($foreignKey['columns'] ?? []), true)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commission_settlements');
    }
};
