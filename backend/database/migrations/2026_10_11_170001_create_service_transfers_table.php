<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M6 服务转移（用户间过户）：service_transfers 表。
 *
 * 状态机：0待对方确认 1双方已确认待生效 2已完成 3已取消 4已拒绝 5已过期。
 * 同一服务存在进行中的转移（status IN(0,1)）由服务层互斥校验保证。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_transfers')) {
            return;
        }

        Schema::create('service_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('status')->default(0)
                ->comment('0待对方确认 1双方已确认待生效 2已完成 3已取消 4已拒绝 5已过期');
            $table->unsignedTinyInteger('initiated_by')
                ->comment('发起方：1转出方 2接收方 3管理员');
            $table->datetime('from_confirmed_at')->nullable();
            $table->datetime('to_confirmed_at')->nullable();
            $table->datetime('expires_at')->comment('确认有效期，超时批量置过期');
            $table->json('risk_flags')->nullable()->comment('生效前最后一次风险校验快照');
            $table->string('reject_reason', 255)->nullable();
            $table->unsignedBigInteger('operator_id')->nullable()->comment('管理员操作时');
            $table->string('trace_id', 64)->nullable();
            $table->timestamps();

            $table->index(['to_user_id', 'status'], 'service_transfers_to_user_status_idx');
            $table->index(['from_user_id', 'status'], 'service_transfers_from_user_status_idx');
            $table->index('service_id', 'service_transfers_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_transfers');
    }
};
