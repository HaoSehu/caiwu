<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 优惠券定向与叠加规则增强（2026-10-11 审查修复 P2-4/P2-6/P3-11，券模板与活动同构）。
 *
 * - requires_product_ids：前置持有条件——用户名下须存在任一指定产品的运行中服务
 *   （本系统为单商品结账，无购物车「同时购买」分支，ZJMF requires_exist 概念收敛为唯一行为）；
 * - only_old_client：仅老客可领/可用（已存在成交购买/续费账单；与 first_order_only 互斥，
 *   创建/更新时交叉拦截）；
 * - is_discount：与会员折扣叠加开关——1=可叠加（现状默认，券以会员折后价为基数）；
 *   0=互斥（本单存在会员折扣减免时该券不应用，对齐 ZJMF is_discount=0 口径）。
 *
 * 消费者：CouponService（领取/使用校验 + buildOwnedCouponPayload 折算守卫）、
 * CouponCampaignService（活动批次透传）。活动生成券经 createCoupon 同口径校验。
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['coupons', 'coupon_campaigns'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'requires_product_ids')) {
                    $table->json('requires_product_ids')->nullable()->after('product_ids')
                        ->comment('前置持有条件：用户名下须持有任一指定产品的运行中服务才可领/用');
                }
                if (! Schema::hasColumn($tableName, 'only_old_client')) {
                    $table->unsignedTinyInteger('only_old_client')->default(0)->after('first_order_only')
                        ->comment('仅老客：0=不限 1=仅已有成交订单的用户可领/用（与首单券互斥）');
                }
                if (! Schema::hasColumn($tableName, 'is_discount')) {
                    $table->unsignedTinyInteger('is_discount')->default(1)->after('only_old_client')
                        ->comment('与会员折扣叠加开关：1=可叠加（默认） 0=互斥（本单有会员折扣减免时券不应用）');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['coupons', 'coupon_campaigns'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (Schema::hasColumn($tableName, 'is_discount')) {
                    $table->dropColumn('is_discount');
                }
                if (Schema::hasColumn($tableName, 'only_old_client')) {
                    $table->dropColumn('only_old_client');
                }
                if (Schema::hasColumn($tableName, 'requires_product_ids')) {
                    $table->dropColumn('requires_product_ids');
                }
            });
        }
    }
};
