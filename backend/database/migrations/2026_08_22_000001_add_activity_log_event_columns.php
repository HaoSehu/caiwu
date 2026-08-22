<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// activity_logs 升级为唯一在线真源：event_id 是新写入与回填的幂等键
// （新写入用 ULID，历史回填用 oplog:{operation_logs.id}），
// stream 区分 access/auth/business/schedule 日志流，trace_id 支撑链路检索。
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_logs', 'event_id')) {
                $table->string('event_id', 40)->nullable()->after('id');
            }

            if (! Schema::hasColumn('activity_logs', 'stream')) {
                $table->string('stream', 20)->nullable()->after('event_id');
            }

            if (! Schema::hasColumn('activity_logs', 'trace_id')) {
                $table->string('trace_id', 64)->nullable()->after('ip_address');
            }
        });

        if (! Schema::hasIndex('activity_logs', 'activity_logs_event_id_unique')) {
            Schema::table('activity_logs', function (Blueprint $table): void {
                // MySQL 唯一索引允许多个 NULL，历史行不回填前不受约束
                $table->unique('event_id', 'activity_logs_event_id_unique');
            });
        }

        if (! Schema::hasIndex('activity_logs', 'activity_logs_stream_created_at_index')) {
            Schema::table('activity_logs', function (Blueprint $table): void {
                $table->index(['stream', 'created_at'], 'activity_logs_stream_created_at_index');
            });
        }

        if (! Schema::hasIndex('activity_logs', 'activity_logs_trace_id_index')) {
            Schema::table('activity_logs', function (Blueprint $table): void {
                $table->index('trace_id', 'activity_logs_trace_id_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        foreach (['activity_logs_trace_id_index', 'activity_logs_stream_created_at_index', 'activity_logs_event_id_unique'] as $indexName) {
            if (Schema::hasIndex('activity_logs', $indexName)) {
                Schema::table('activity_logs', function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }

        Schema::table('activity_logs', function (Blueprint $table): void {
            foreach (['event_id', 'stream', 'trace_id'] as $columnName) {
                if (Schema::hasColumn('activity_logs', $columnName)) {
                    $table->dropColumn($columnName);
                }
            }
        });
    }
};
