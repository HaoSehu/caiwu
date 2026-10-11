<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M3 工单部门体系：转派流水，记录部门/处理人变更轨迹与操作人。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_transfer_logs')) {
            return;
        }

        Schema::create('ticket_transfer_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->comment('工单 ID');
            $table->unsignedBigInteger('from_department_id')->nullable()->comment('转出部门 ID，NULL=原无部门');
            $table->unsignedBigInteger('to_department_id')->nullable()->comment('转入部门 ID，NULL=转出后无部门');
            $table->unsignedBigInteger('from_assignee_id')->nullable()->comment('原处理人 admin_users.id');
            $table->unsignedBigInteger('to_assignee_id')->nullable()->comment('新处理人 admin_users.id，NULL=待自动分派/无成员');
            $table->string('reason', 255)->nullable()->comment('转派原因');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人 admin_users.id，NULL=系统');
            $table->timestamp('created_at')->nullable();

            $table->index(['ticket_id', 'created_at'], 'idx_ticket_transfer_logs_ticket_created');
            $table->foreign('ticket_id', 'fk_ticket_transfer_logs_ticket')
                ->references('id')->on('tickets')->cascadeOnDelete();
            $table->foreign('from_department_id', 'fk_ticket_transfer_logs_from_department')
                ->references('id')->on('ticket_departments')->nullOnDelete();
            $table->foreign('to_department_id', 'fk_ticket_transfer_logs_to_department')
                ->references('id')->on('ticket_departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_transfer_logs');
    }
};
