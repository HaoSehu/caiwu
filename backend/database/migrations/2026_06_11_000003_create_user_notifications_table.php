<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 50)->comment('消息类型：order_paid/service_renew_reminder/service_expire_reminder 等');
            $table->string('title', 191);
            $table->text('content')->nullable();
            $table->string('link', 255)->nullable()->comment('点击跳转的前端路由');
            $table->json('data')->nullable()->comment('附加业务数据');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
