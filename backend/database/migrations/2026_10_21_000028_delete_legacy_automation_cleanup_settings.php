<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 清理 automation 组的历史退款/待付款保留时长配置键。
 *
 * 背景：这三个键从未被业务代码读取。待付款清理的真实阈值由
 * CheckoutSecurityService::paymentSessionTtlSeconds()（PAYMENT_SESSION_TTL_SECONDS = 300）
 * 决定，与该配置无关；管理端表单展示的「小时/天数」因此是误导性配置。
 * 对应代码（SettingService 默认值、SettingsSeeder、admin 表单）已在本轮移除，
 * 但数据库 settings 表中可能仍残留历史行，需一并清理，避免后台再次出现无消费方的键。
 *
 * 仅删除指定键，不触碰 automation 组其它配置。
 */
return new class extends Migration
{
    private const LEGACY_KEYS = [
        'pending_order_cleanup_after_hours',
        'pending_order_cleanup_after_days',
        'pending_recharge_cleanup_after_days',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')
            ->where('group_key', 'automation')
            ->whereIn('item_key', self::LEGACY_KEYS)
            ->delete();
    }

    public function down(): void
    {
        // 配置键已无消费方，回滚不重建，避免恢复误导性配置。
    }
};
