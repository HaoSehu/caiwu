<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 财务写操作幂等记录：把幂等从 Redis 临时键升级为可追溯的持久事实。
 *
 * 语义：
 * - 同 (scope, actor_type, actor_id, request_key) + 同 request_hash → 重放，返回 result_payload；
 * - 同键异 hash → 409 拒绝，不执行也不覆盖；
 * - 记录在业务事务之外创建/更新，业务回滚不带走幂等事实。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('finance_operations')) {
            return;
        }

        Schema::create('finance_operations', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 40)->comment('操作域：checkout/invoice_pay/recharge/balance_adjust/invoice_refund');
            $table->string('request_key', 128)->comment('客户端幂等键（X-Idempotency-Key）');
            $table->char('request_hash', 64)->comment('规范化请求 SHA256；同键不同内容拒绝');
            $table->string('actor_type', 24)->comment('发起者类型：admin/member/system');
            $table->unsignedBigInteger('actor_id')->comment('发起者主键');
            $table->string('resource_type', 40)->nullable()->comment('结果资源类型：invoice/payment/recharge/refund');
            $table->unsignedBigInteger('resource_id')->nullable()->comment('结果资源主键');
            $table->string('status', 16)->default('processing')->comment('状态：processing/succeeded/failed');
            $table->json('result_payload')->nullable()->comment('成功结果快照，用于同键重放');
            $table->string('error_code', 64)->nullable()->comment('失败错误码');
            $table->string('error_msg', 500)->nullable()->comment('失败摘要');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪号');
            $table->dateTime('started_at')->comment('开始处理时间');
            $table->dateTime('finished_at')->nullable()->comment('结束时间');
            $table->timestamps();

            $table->unique(
                ['scope', 'actor_type', 'actor_id', 'request_key'],
                'finance_operations_scope_actor_key_unique'
            );
            $table->index(['status', 'started_at'], 'finance_operations_status_time_index');
            $table->index(['resource_type', 'resource_id'], 'finance_operations_resource_index');
            $table->index('trace_id', 'finance_operations_trace_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_operations');
    }
};
