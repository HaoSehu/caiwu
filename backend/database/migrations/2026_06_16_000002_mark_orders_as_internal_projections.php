<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'projection_type')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('projection_type', 32)
                ->default('provisioning')
                ->comment('内部投影类型：provisioning=开通投影')
                ->after('trace_id');
            $table->index('projection_type', 'orders_projection_type_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'projection_type')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_projection_type_idx');
            $table->dropColumn('projection_type');
        });
    }
};
