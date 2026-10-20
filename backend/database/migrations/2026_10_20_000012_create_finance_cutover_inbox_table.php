<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 切换窗口期支付回调暂存（收件箱）。
 *
 * 背景：一次性切换需要停机只读，但三方支付回调不受维护页控制。若直接 503，
 * 已付款用户的回调只能依赖网关重试，存在丢失风险。
 *
 * 做法：窗口内回调路由不落业务表，只把原始报文写入本表（不校验、不入账）；
 * 解除维护后由 finance:replay-cutover-inbox 按原验签逻辑重放，重放走既有幂等。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_cutover_inbox')) {
            return;
        }

        Schema::create('finance_cutover_inbox', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 40)->comment('回调渠道标识：gateway key 或 notify token 前缀');
            $table->string('notification_id', 128)->comment('回调唯一标识；无原生 id 时用报文摘要');
            $table->string('request_uri', 255)->comment('原始请求 URI，重放时还原路由');
            $table->longText('payload')->comment('原始报文全文，不裁剪');
            $table->json('headers')->nullable()->comment('必要的请求头快照');
            $table->string('status', 16)->default('pending')->comment('状态：pending/replayed/failed');
            $table->dateTime('received_at')->comment('接收时间');
            $table->dateTime('replayed_at')->nullable()->comment('重放完成时间');
            $table->string('error_msg', 500)->nullable()->comment('重放失败摘要');
            $table->timestamps();

            $table->unique(['channel', 'notification_id'], 'finance_cutover_inbox_channel_notification_unique');
            $table->index(['status', 'received_at'], 'finance_cutover_inbox_status_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_cutover_inbox');
    }
};
