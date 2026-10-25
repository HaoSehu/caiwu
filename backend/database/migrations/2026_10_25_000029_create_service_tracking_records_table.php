<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 服务实例「历史跟踪」备注：管理员按实例记录的跟进留言与截图，
// 操作人固定为提交时的登录管理员，随记录留档（不参与后续改名同步）。
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_tracking_records')) {
            return;
        }

        Schema::create('service_tracking_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('tracked_at')->comment('跟踪时间');
            $table->text('content')->comment('留言内容');
            $table->json('images')->nullable()->comment('截图列表');
            $table->unsignedBigInteger('operator_id')->default(0)->comment('操作管理员ID');
            $table->string('operator_name', 64)->default('')->comment('操作管理员名称快照');
            $table->timestamps();

            $table->index(['service_id', 'tracked_at'], 'service_tracking_service_time_idx');
            $table->index('user_id', 'service_tracking_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tracking_records');
    }
};
