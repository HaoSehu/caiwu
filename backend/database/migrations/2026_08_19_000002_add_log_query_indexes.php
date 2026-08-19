<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operation_logs') && ! Schema::hasIndex('operation_logs', 'operation_logs_action_created_at_index')) {
            Schema::table('operation_logs', function (Blueprint $table): void {
                $table->index(['action', 'created_at'], 'operation_logs_action_created_at_index');
            });
        }

        if (Schema::hasTable('schedule_run_logs') && ! Schema::hasIndex('schedule_run_logs', 'schedule_run_logs_started_at_id_index')) {
            Schema::table('schedule_run_logs', function (Blueprint $table): void {
                $table->index(['started_at', 'id'], 'schedule_run_logs_started_at_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('operation_logs') && Schema::hasIndex('operation_logs', 'operation_logs_action_created_at_index')) {
            Schema::table('operation_logs', function (Blueprint $table): void {
                $table->dropIndex('operation_logs_action_created_at_index');
            });
        }

        if (Schema::hasTable('schedule_run_logs') && Schema::hasIndex('schedule_run_logs', 'schedule_run_logs_started_at_id_index')) {
            Schema::table('schedule_run_logs', function (Blueprint $table): void {
                $table->dropIndex('schedule_run_logs_started_at_id_index');
            });
        }
    }
};
