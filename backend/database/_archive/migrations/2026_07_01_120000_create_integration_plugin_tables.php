<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_plugins', function (Blueprint $table): void {
            $table->id();
            $table->string('domain', 32);
            $table->string('slug', 120);
            $table->string('plugin_key', 120);
            $table->string('name', 120);
            $table->string('version', 32)->default('1.0.0');
            $table->string('provider_class', 255)->nullable();
            $table->string('entry_class', 255);
            $table->json('capabilities_json')->nullable();
            $table->json('config_schema_json')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'slug'], 'integration_plugins_domain_slug_unique');
            $table->unique(['domain', 'plugin_key'], 'integration_plugins_domain_key_unique');
            $table->index(['domain', 'status'], 'integration_plugins_domain_status_index');
        });

        Schema::create('integration_plugin_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plugin_id')->constrained('integration_plugins')->cascadeOnDelete();
            $table->json('config_json')->nullable();
            $table->longText('secret_json')->nullable();
            $table->json('has_secret_json')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('plugin_id', 'integration_plugin_configs_plugin_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_plugin_configs');
        Schema::dropIfExists('integration_plugins');
    }
};
