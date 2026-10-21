<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property array<int, int>|null $requires_product_ids 前置持有条件：用户名下须持有任一指定产品的运行中服务
 * @property bool $only_old_client 仅老客可领/用（与 first_order_only 互斥）
 * @property bool $is_discount 与会员折扣叠加开关：true=可叠加（默认） false=互斥
 */
class Coupon extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'coupon_campaign_id',
        'name',
        'code',
        'description',
        'distribution_type',
        'discount_scope',
        'discount_type',
        'discount_value',
        'min_amount',
        'max_discount_amount',
        'billing_cycles',
        'product_ids',
        'requires_product_ids',
        'first_order_only',
        'only_old_client',
        'is_discount',
        'total_usage_limit',
        'per_user_limit',
        'used_count',
        'status',
        'sort_order',
        'starts_at',
        'expires_at',
        'remark',
        'operator',
        'trace_id',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'coupon_campaign_id' => 'integer',
            'distribution_type' => 'string',
            'discount_scope' => 'string',
            'billing_cycles' => 'array',
            'product_ids' => 'array',
            'requires_product_ids' => 'array',
            'first_order_only' => 'boolean',
            'only_old_client' => 'boolean',
            'is_discount' => 'boolean',
            'total_usage_limit' => 'integer',
            'per_user_limit' => 'integer',
            'used_count' => 'integer',
            'status' => 'integer',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function couponCampaign(): BelongsTo
    {
        return $this->belongsTo(CouponCampaign::class);
    }

    public function userCoupons(): HasMany
    {
        return $this->hasMany(UserCoupon::class);
    }
}
