<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 消息逐次投递尝试（对齐 howstack-bos message_delivery_attempts）。
 *
 * 背景：邮件队列任务重试（tries=3）每次都会重新走 sendEmail 并新建一条
 * message_logs，多次尝试之间没有血缘；短信同步发送也无法区分「一次逻辑投递」
 * 与「一次网络调用」。
 *
 * 本批改动：
 * - message_logs.delivery_key：队列任务构造时生成、跨重试保持不变，用于把
 *   多次尝试归并到同一条逻辑消息；非队列渠道为 NULL。
 * - message_delivery_attempts：每次发送调用一行，记录尝试序号、状态、异常类名、
 *   失败摘要与耗时；(message_log_id, attempt_no) 唯一，重复处理自动跳过。
 *
 * 尝试表不保存收件地址、正文或凭据；正文与收件人仍在 message_logs（管理端真实审计口径）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('message_logs') && ! Schema::hasColumn('message_logs', 'delivery_key')) {
            Schema::table('message_logs', function (Blueprint $table): void {
                $table->string('delivery_key', 36)
                    ->nullable()
                    ->after('trace_id')
                    ->comment('跨队列重试稳定的投递键；非队列渠道为 NULL');
                $table->index('delivery_key', 'message_logs_delivery_key_index');
            });
        }

        if (! Schema::hasTable('message_delivery_attempts')) {
            Schema::create('message_delivery_attempts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('message_log_id')->comment('message_logs.id');
                $table->unsignedInteger('attempt_no')->comment('同一投递内的尝试序号，从 1 开始');
                $table->string('status', 24)->comment('尝试结果: success, failed');
                $table->string('error_code', 64)->default('')->comment('异常类名，不含厂商响应正文');
                $table->string('error_msg', 500)->nullable()->comment('失败摘要，最多 500 字');
                $table->unsignedInteger('duration_ms')->default(0)->comment('本次尝试耗时毫秒');
                $table->dateTime('created_at')->comment('尝试开始时间');
                $table->dateTime('finished_at')->nullable()->comment('尝试结束时间');

                $table->unique(['message_log_id', 'attempt_no'], 'message_delivery_attempts_unique');
                $table->index(['message_log_id', 'created_at'], 'message_delivery_attempts_log_time_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('message_delivery_attempts');

        if (Schema::hasTable('message_logs') && Schema::hasColumn('message_logs', 'delivery_key')) {
            Schema::table('message_logs', function (Blueprint $table): void {
                $table->dropIndex('message_logs_delivery_key_index');
                $table->dropColumn('delivery_key');
            });
        }
    }
};
