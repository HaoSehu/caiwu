<?php

declare(strict_types=1);

use App\Services\Ticket\TicketDepartmentService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M3 工单部门体系：tickets 增加 department_id 真源列，并按存量 department 字符串
// 幂等回填（distinct 非空字符串各建一条部门，已存在跳过；重复执行不重复建、不重复回填）。
// 旧字符串列 department 保留为冗余展示列，回填/转派时同步为部门名。
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'department_id')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->unsignedBigInteger('department_id')
                    ->nullable()
                    ->after('department')
                    ->comment('所属部门 ID（ticket_departments），NULL=未归属');
                $table->index('department_id', 'idx_tickets_department_id');
                $table->foreign('department_id', 'fk_tickets_department_id')
                    ->references('id')->on('ticket_departments')->nullOnDelete();
            });
        }

        TicketDepartmentService::backfillLegacyDepartments();
    }

    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'department_id')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropForeign('fk_tickets_department_id');
                $table->dropIndex('idx_tickets_department_id');
                $table->dropColumn('department_id');
            });
        }
    }
};
