<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M3 工单部门体系：部门实体表，替代纯字符串 department 列的真源。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_departments')) {
            return;
        }

        Schema::create('ticket_departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->unique()->comment('部门名称');
            $table->string('description', 255)->nullable()->comment('部门描述');
            $table->tinyInteger('auto_assign_mode')->default(0)->comment('分派模式：0手动 1轮询 2最少负载');
            $table->tinyInteger('is_enabled')->default(1)->comment('0=禁用 1=启用');
            $table->integer('sort_order')->default(0)->comment('排序值，越小越靠前');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_departments');
    }
};
