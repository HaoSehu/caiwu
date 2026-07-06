<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 20);
            $table->string('code', 32);
            $table->string('name', 120);
            $table->string('description', 500)->default('');
            $table->string('audience', 20)->default('user');
            $table->string('subject', 255)->nullable();
            $table->longText('content');
            $table->json('variables_json')->nullable();
            $table->json('provider_variables_json')->nullable();
            $table->string('provider_template_id', 120)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['channel', 'code'], 'notification_templates_channel_code_unique');
            $table->index(['channel', 'audience', 'is_enabled'], 'notification_templates_channel_audience_enabled_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
