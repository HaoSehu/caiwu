<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// 推广大使档位：邀请返利比例的独立配置载体，与会员等级解耦；
// 用户由管理员在详情页指派，未指派时按全局 referral.reward_rate 兜底。
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('promotion_ambassadors')) {
            Schema::create('promotion_ambassadors', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 50)->comment('大使名称');
                $table->decimal('reward_rate', 5, 2)->default(0.00)->comment('返利比例（%）0-100');
                $table->tinyInteger('status')->default(1)->comment('0=禁用 1=启用');
                $table->string('remark', 255)->nullable()->comment('备注');
                $table->timestamps();

                $table->index('status', 'idx_promotion_ambassador_status');
            });
        }

        // 默认大使：比例与全局 referral.reward_rate 默认值一致，保证存量用户费率不变
        $hasDefault = DB::table('promotion_ambassadors')->where('name', '默认大使')->exists();
        if (! $hasDefault) {
            DB::table('promotion_ambassadors')->insert([
                'name' => '默认大使',
                'reward_rate' => 10.00,
                'status' => 1,
                'remark' => '系统初始化档位，继承原会员等级返利默认比例',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_ambassadors');
    }
};
