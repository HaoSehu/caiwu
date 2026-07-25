<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedule_run_logs')) {
            return;
        }

        Schema::create('schedule_run_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('task_name', 100);
            $table->string('status', 20)->default('success');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('summary')->nullable();
            $table->text('error_msg')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('task_name');
            $table->index(['task_name', 'created_at']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_run_logs');
    }
};
