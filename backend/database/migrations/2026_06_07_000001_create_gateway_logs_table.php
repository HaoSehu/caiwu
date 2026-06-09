<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_logs', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50)->comment('网关标识: alipay_f2f, wechat_native 等');
            $table->string('action', 50)->comment('操作: precreate, notify, query, refund');
            $table->string('out_trade_no', 128)->nullable()->index()->comment('商户订单号');
            $table->string('trade_no', 128)->nullable()->comment('第三方交易号');
            $table->unsignedBigInteger('invoice_id')->nullable()->index()->comment('关联账单ID');
            $table->json('request_data')->nullable()->comment('请求数据(脱敏后)');
            $table->json('response_data')->nullable()->comment('响应数据');
            $table->string('result_status', 20)->default('unknown')->comment('结果: success, failed, pending, unknown');
            $table->text('error_msg')->nullable()->comment('错误信息');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['gateway', 'action']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_logs');
    }
};
