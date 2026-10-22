<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 履约任务租约（FUL-1）：为 services 增加实例级开通租约列。
 *
 * 背景：支付后开通已改为同步执行，同一订单由 Cache::lock 与队列 WithoutOverlapping
 * 两道「按订单」互斥保护；但失败补偿、人工重试、支付回调和上游回调都可能绕过订单锁
 * 直接对同一个 service 发起开通，同一实例被并发重复提交上游 checkout 会重复下单。
 * 租约按「实例」维度加第三道闸：只有持有当前租约令牌的执行者才允许回写开通结果。
 *
 * - provision_lease_token：租约持有者令牌（32 位十六进制随机串，长度 48 留足余量）。
 *   非空表示有执行者正在开通，回写前必须与本列比对，令牌不符即视为租约已被他人接管。
 * - provision_lease_until：租约到期时间。到期即允许其他执行者抢占，避免进程崩溃后
 *   实例被永久锁死；列上有索引，供超时巡检与抢占条件直接命中。
 * - provision_attempts：累计已发生的开通尝试次数（不含 dry-run），用于「重试次数耗尽」
 *   打标与人工判断，和 provision_data.retry_count 相互独立（后者只统计人工补偿命令）。
 *
 * 三列均可空/有默认值，存量服务数据不受影响；列存在性有守卫，兼容未升级的存量库。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        Schema::table('services', function (Blueprint $table): void {
            if (! Schema::hasColumn('services', 'provision_lease_token')) {
                $table->char('provision_lease_token', 48)->nullable()->comment('开通租约令牌，非空表示有执行者正在开通');
            }

            if (! Schema::hasColumn('services', 'provision_lease_until')) {
                $table->dateTime('provision_lease_until')->nullable()->comment('开通租约到期时间，过期允许他人抢占');
            }

            if (! Schema::hasColumn('services', 'provision_attempts')) {
                $table->unsignedInteger('provision_attempts')->default(0)->comment('累计开通尝试次数');
            }
        });

        if (! Schema::hasIndex('services', 'services_provision_lease_until_index')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->index('provision_lease_until', 'services_provision_lease_until_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        if (Schema::hasIndex('services', 'services_provision_lease_until_index')) {
            Schema::table('services', function (Blueprint $table): void {
                $table->dropIndex('services_provision_lease_until_index');
            });
        }

        Schema::table('services', function (Blueprint $table): void {
            if (Schema::hasColumn('services', 'provision_attempts')) {
                $table->dropColumn('provision_attempts');
            }

            if (Schema::hasColumn('services', 'provision_lease_until')) {
                $table->dropColumn('provision_lease_until');
            }

            if (Schema::hasColumn('services', 'provision_lease_token')) {
                $table->dropColumn('provision_lease_token');
            }
        });
    }
};
