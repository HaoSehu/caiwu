<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'type')) {
            return;
        }

        DB::table('orders')
            ->where('type', 'addon')
            ->update(['type' => 'upgrade']);
    }

    public function down(): void
    {
        // Intentionally no-op: upgrade is the new canonical order type and may
        // include data that was never addon, so converting back would be unsafe.
    }
};
