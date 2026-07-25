<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 20)->default('system')->comment('操作者类型: admin, client, system, sub_account');
            $table->unsignedBigInteger('actor_id')->nullable()->index()->comment('操作者ID');
            $table->string('actor_name', 100)->default('')->comment('操作者名称快照');
            $table->string('module', 50)->comment('模块: invoice, order, service, user, product, ticket, coupon, system');
            $table->string('action', 100)->comment('动作描述: create, pay, refund, suspend, terminate 等');
            $table->text('description')->comment('可读描述');
            $table->string('subject_type', 50)->nullable()->comment('关联对象类型: invoice, service, order, user, ticket');
            $table->unsignedBigInteger('subject_id')->nullable()->comment('关联对象ID');
            $table->json('context')->nullable()->comment('附加结构化上下文');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['module', 'action']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
