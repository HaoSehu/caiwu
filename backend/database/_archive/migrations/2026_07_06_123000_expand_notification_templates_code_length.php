<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        DB::statement('ALTER TABLE notification_templates MODIFY code VARCHAR(64) NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_templates')) {
            return;
        }

        DB::statement('ALTER TABLE notification_templates MODIFY code VARCHAR(32) NOT NULL');
    }
};
