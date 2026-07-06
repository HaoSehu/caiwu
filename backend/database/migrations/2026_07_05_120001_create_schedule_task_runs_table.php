<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedule_task_runs')) {
            return;
        }

        Schema::create('schedule_task_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_tick_id')->nullable()->constrained('schedule_ticks')->cascadeOnDelete();
            $table->string('task_key', 120);
            $table->string('task_name', 160);
            $table->string('rule_description', 160)->nullable();
            $table->string('source', 40)->default('heartbeat');
            $table->string('queue', 80)->nullable();
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('summary')->nullable();
            $table->text('error_msg')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['schedule_tick_id', 'task_key', 'source'], 'schedule_task_runs_tick_task_source_unique');
            $table->index(['task_key', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_task_runs');
    }
};
