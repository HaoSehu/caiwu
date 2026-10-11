<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M3 工单部门体系：客服（admin_users）与部门的多对多归属，部门级数据范围依据。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_department_staff')) {
            return;
        }

        Schema::create('ticket_department_staff', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('department_id')->comment('部门 ID');
            $table->unsignedBigInteger('admin_user_id')->comment('客服（admin_users）ID');
            $table->tinyInteger('is_leader')->default(0)->comment('0=成员 1=部门负责人');
            $table->timestamps();

            $table->unique(['department_id', 'admin_user_id'], 'uk_ticket_department_staff');
            $table->index('admin_user_id', 'idx_ticket_department_staff_admin_user');
            $table->foreign('department_id', 'fk_ticket_department_staff_department')
                ->references('id')->on('ticket_departments')->cascadeOnDelete();
            $table->foreign('admin_user_id', 'fk_ticket_department_staff_admin_user')
                ->references('id')->on('admin_users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_department_staff');
    }
};
