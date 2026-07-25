<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('schedule_ticks')) {
            return;
        }

        Schema::create('schedule_ticks', function (Blueprint $table) {
            $table->id();
            $table->timestamp('slot_started_at')->unique();
            $table->unsignedBigInteger('global_number')->unique();
            $table->unsignedTinyInteger('daily_index')->index();
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_ticks');
    }
};
