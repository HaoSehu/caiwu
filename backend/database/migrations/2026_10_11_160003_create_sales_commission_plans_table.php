<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 提成方案：同一时段可存在多条，计提时取最新启用的有效期覆盖方案。
// 费率快照哲学：方案配置变更不追溯历史账单，计提时快照落 sales_commissions.plan_snapshot。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_commission_plans')) {
            return;
        }

        Schema::create('sales_commission_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->comment('方案名称');
            $table->tinyInteger('basis')->default(1)->comment('计提基数：1实付金额 2订单金额');
            $table->tinyInteger('is_enabled')->default(1)->comment('0=停用 1=启用');
            $table->date('valid_from')->nullable()->comment('生效日期，空=不限');
            $table->date('valid_to')->nullable()->comment('失效日期，空=不限');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
            $table->softDeletes()->comment('删除时间');

            $table->index('is_enabled', 'sales_commission_plans_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_commission_plans');
    }
};
