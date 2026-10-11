<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M9 批量群发逐用户发送结果表。
 *
 * unique(mass_send_id, user_id) 保证同一任务目标唯一；
 * email 为发送时快照；error 记录单用户失败原因（不阻塞分片）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mass_send_targets')) {
            return;
        }

        Schema::create('mass_send_targets', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('mass_send_id')->comment('所属群发任务');
            $table->unsignedBigInteger('user_id')->comment('目标用户 users.id');
            $table->string('email', 190)->nullable()->comment('发送时邮箱快照（站内信通道可为 NULL）');
            $table->tinyInteger('status')->default(0)->comment('0待发 1成功 2失败');
            $table->string('error', 255)->nullable()->comment('失败原因');
            $table->dateTime('sent_at')->nullable()->comment('发送完成时间');
            $table->timestamps();

            $table->foreign('mass_send_id')->references('id')->on('mass_sends')->cascadeOnDelete();
            $table->unique(['mass_send_id', 'user_id'], 'uk_mass_send_targets_send_user');
            $table->index(['mass_send_id', 'status'], 'idx_mass_send_targets_send_status');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('mass_send_targets')) {
            Schema::dropIfExists('mass_send_targets');
        }
    }
};
