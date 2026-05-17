<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_logs')) {
            return;
        }

        Schema::table('notification_logs', function (Blueprint $table): void {
            if (Schema::hasIndex('notification_logs', 'notification_logs_origin_unique')) {
                $table->dropUnique('notification_logs_origin_unique');
            }

            if (! Schema::hasIndex('notification_logs', 'notification_logs_origin_idx')) {
                $table->index(['origin_type', 'origin_id'], 'notification_logs_origin_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_logs')) {
            return;
        }

        Schema::table('notification_logs', function (Blueprint $table): void {
            if (Schema::hasIndex('notification_logs', 'notification_logs_origin_idx')) {
                $table->dropIndex('notification_logs_origin_idx');
            }

            if (! Schema::hasIndex('notification_logs', 'notification_logs_origin_unique')) {
                $table->unique(['origin_type', 'origin_id'], 'notification_logs_origin_unique');
            }
        });
    }
};
