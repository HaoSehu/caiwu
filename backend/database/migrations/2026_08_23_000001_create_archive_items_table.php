<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// V2 归档协议元数据：每个归档物一条记录，状态机
// planned -> staging -> verified -> published -> purging -> purged
//   \-> failed / needs_recovery
// 源数据只在 published（manifest 校验通过并原子发布）后才允许分块删除。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('archive_items')) {
            return;
        }

        Schema::create('archive_items', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id', 64);
            $table->string('table_name', 64);
            $table->string('status', 24)->default('planned');
            $table->timestamp('cutoff_at')->nullable();
            $table->unsignedBigInteger('id_min')->nullable();
            $table->unsignedBigInteger('id_max')->nullable();
            $table->unsignedBigInteger('expected_rows')->default(0);
            $table->unsignedBigInteger('exported_rows')->default(0);
            $table->unsignedBigInteger('deleted_rows')->default(0);
            $table->text('part_path')->nullable();
            $table->text('published_path')->nullable();
            $table->text('manifest_path')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();

            $table->index('batch_id', 'archive_items_batch_idx');
            $table->index(['table_name', 'status'], 'archive_items_table_status_idx');
            $table->index('status', 'archive_items_status_idx');
            $table->index('created_at', 'archive_items_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_items');
    }
};
