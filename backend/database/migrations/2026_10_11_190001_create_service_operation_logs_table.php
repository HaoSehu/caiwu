<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 服务操作留痕表（service_operation_logs）：App\Models\ServiceOperationLog 的真实落库表。
// 写入方：ServiceTransferService::writeOperationLog（过户完成留痕）等，均带 hasTable 守卫；
// 本迁移补齐该表，使留痕与读取链路（ServiceDetailService / ServiceConsoleController）真正生效。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_operation_logs')) {
            return;
        }

        Schema::create('service_operation_logs', function (Blueprint $table): void {
            $table->id()->comment('主键');
            $table->unsignedBigInteger('service_instance_id')->comment('服务实例 service_instances.id');
            $table->string('operation_type', 64)->comment('操作类型，如 service_transfer');
            $table->json('request_payload_json')->nullable()->comment('请求参数快照');
            $table->json('response_payload_json')->nullable()->comment('响应结果快照');
            $table->string('result_status', 32)->nullable()->comment('执行结果状态，如 success');
            $table->string('provider_request_id', 191)->nullable()->comment('上游供应商请求ID');
            $table->string('error_message', 1000)->nullable()->comment('失败原因');
            $table->string('operator_type', 32)->nullable()->comment('操作方类型：admin/client/system');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人ID（管理员/用户）');
            $table->string('trace_id', 64)->nullable()->comment('链路追踪ID');
            $table->datetime('executed_at')->nullable()->comment('操作发生时间');
            $table->datetime('created_at')->nullable()->comment('创建时间');
            $table->datetime('updated_at')->nullable()->comment('更新时间');

            $table->index('service_instance_id', 'service_operation_logs_service_instance_id_index');
            $table->index(['service_instance_id', 'operation_type'], 'service_operation_logs_instance_type_idx');
            $table->index(['service_instance_id', 'executed_at'], 'service_operation_logs_instance_executed_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_operation_logs');
    }
};
