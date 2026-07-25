<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('schedule_task_runs')) {
            return;
        }

        Schema::table('schedule_task_runs', function (Blueprint $table): void {
            $table->index(
                ['task_key', 'status', 'queued_at'],
                'schedule_task_runs_active_lookup_index',
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('schedule_task_runs')) {
            return;
        }

        Schema::table('schedule_task_runs', function (Blueprint $table): void {
            $table->dropIndex('schedule_task_runs_active_lookup_index');
        });
    }
};
