<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 优惠券删除改软删（数据库结构分析报告 P1-7）。
 *
 * 原实现 deleteCoupon 会物理删除 coupons 行并连带 delete() 掉用户的未使用领取记录，
 * 领取/回收历史永久消失。本迁移给 coupons 补 deleted_at（SoftDeletes 列），
 * 让删除变成「下线 + 隐藏」，历史行与 user_coupons 关联全部保留。
 *
 * 注意：user_coupons 不加 deleted_at——回收语义用 status=REVOKED 表达（已有常量），
 * 软删列会和状态机形成第二套真相。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupons') || Schema::hasColumn('coupons', 'deleted_at')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table): void {
            $table->softDeletes()->after('updated_at');
            $table->index(['deleted_at', 'status'], 'coupons_deleted_status_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coupons') || ! Schema::hasColumn('coupons', 'deleted_at')) {
            return;
        }

        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropIndex('coupons_deleted_status_idx');
            $table->dropSoftDeletes();
        });
    }
};
