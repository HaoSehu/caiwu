<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 服务新增续费持续优惠绑定列（优惠券持续优惠批 2026-10-11）。
 *
 * 「持续优惠」（discount_scope=recurring）语义对齐营销承诺：新购使用该类券后，
 * 服务绑定优惠快照，后续每次续费自动按折后价计算，不恢复原价（对齐 ZJMF
 * host.promoid + host.amount 折后口径）。快照驱动：
 * - renew_coupon_id：绑定的优惠券 ID（溯源用，不参与运行时校验）
 * - renew_coupon_snapshot：优惠参数快照（discount_type/discount_value/max_discount_amount/
 *   coupon_code/coupon_name/discount_label/bound_invoice_no/bound_at），
 *   续费折算只读快照，券后续停用/删除不影响已绑定服务（营销口径「续费不涨价」）
 *
 * 写入者：ProvisionService 服务创建点（新购账单含 recurring 券时落绑定）；
 * 消费者：ServiceRenewService（续费建单自动折算 + 报价展示）。
 * 不加索引：renew_coupon_id 仅溯源展示，不作为查询条件。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('services')) {
            Schema::table('services', function (Blueprint $table): void {
                if (! Schema::hasColumn('services', 'renew_coupon_id')) {
                    $table->unsignedBigInteger('renew_coupon_id')->nullable()->after('locked_pricing')
                        ->comment('续费持续优惠绑定的优惠券 ID（溯源，运行时以快照为准）');
                }
                if (! Schema::hasColumn('services', 'renew_coupon_snapshot')) {
                    $table->json('renew_coupon_snapshot')->nullable()->after('renew_coupon_id')
                        ->comment('续费持续优惠参数快照（discount_type/discount_value/max_discount_amount 等，续费自动折算依据）');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('services')) {
            Schema::table('services', function (Blueprint $table): void {
                if (Schema::hasColumn('services', 'renew_coupon_snapshot')) {
                    $table->dropColumn('renew_coupon_snapshot');
                }
                if (Schema::hasColumn('services', 'renew_coupon_id')) {
                    $table->dropColumn('renew_coupon_id');
                }
            });
        }
    }
};
