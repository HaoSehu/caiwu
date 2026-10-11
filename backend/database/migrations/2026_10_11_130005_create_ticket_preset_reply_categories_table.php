<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M2 工单预设回复库：模板分类。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_preset_reply_categories')) {
            return;
        }

        Schema::create('ticket_preset_reply_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->unique()->comment('分类名称');
            $table->integer('sort_order')->default(0)->comment('排序值，越小越靠前');
            $table->tinyInteger('is_enabled')->default(1)->comment('0=禁用 1=启用');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_preset_reply_categories');
    }
};
