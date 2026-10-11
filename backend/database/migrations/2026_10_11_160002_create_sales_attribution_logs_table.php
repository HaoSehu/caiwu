<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M5 客户归属变更留痕：防"抢客"纠纷，归属每次变更记录前后销售与操作人。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_attribution_logs')) {
            return;
        }

        Schema::create('sales_attribution_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('客户 user id');
            $table->unsignedBigInteger('from_admin_id')->nullable()->comment('原归属销售 admin_users.id，空=此前未归属');
            $table->unsignedBigInteger('to_admin_id')->nullable()->comment('新归属销售 admin_users.id，空=解除归属');
            $table->string('reason', 255)->nullable()->comment('变更原因');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作管理员 admin_users.id');
            $table->datetime('created_at')->nullable()->comment('创建时间');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('from_admin_id')->references('id')->on('admin_users')->nullOnDelete();
            $table->foreign('to_admin_id')->references('id')->on('admin_users')->nullOnDelete();

            $table->index(['user_id', 'created_at'], 'sales_attribution_logs_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_attribution_logs');
    }
};
