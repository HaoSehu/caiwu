<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('media_files')) {
            return;
        }

        Schema::table('media_files', function (Blueprint $table): void {
            if (! Schema::hasColumn('media_files', 'disk')) {
                $table->string('disk', 24)->default('local')->after('group')->comment('存储驱动标识：local 或远程存储插件 slug');
            }

            if (! Schema::hasColumn('media_files', 'object_key')) {
                $table->string('object_key', 500)->nullable()->after('disk')->comment('远程对象键（完整键，含 media/ 前缀段）；本地行为 NULL');
            }
        });

        if (! Schema::hasIndex('media_files', 'media_files_disk_index')) {
            Schema::table('media_files', function (Blueprint $table): void {
                $table->index('disk', 'media_files_disk_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('media_files')) {
            return;
        }

        if (Schema::hasIndex('media_files', 'media_files_disk_index')) {
            Schema::table('media_files', function (Blueprint $table): void {
                $table->dropIndex('media_files_disk_index');
            });
        }

        Schema::table('media_files', function (Blueprint $table): void {
            if (Schema::hasColumn('media_files', 'object_key')) {
                $table->dropColumn('object_key');
            }

            if (Schema::hasColumn('media_files', 'disk')) {
                $table->dropColumn('disk');
            }
        });
    }
};
