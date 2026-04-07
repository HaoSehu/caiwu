<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\OrderInvoiceNoGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_no',
        'user_id',
        'product_id',
        'product_name_snapshot',
        'product_type_snapshot',
        'service_id',
        'type',
        'coupon_id',
        'user_coupon_id',
        'coupon_code',
        'amount',
        'discount',
        'paid_amount',
        'billing_cycle',
        'quantity',
        'config_snapshot',
        'config_pricing_snapshot',
        'coupon_snapshot',
        'status',
        'paid_at',
        'remark',
        'operator',
        'trace_id',
    ];

    protected function casts(): array
    {
        return [
            'coupon_id' => 'integer',
            'user_coupon_id' => 'integer',
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'quantity' => 'integer',
            'config_snapshot' => 'array',
            'config_pricing_snapshot' => 'array',
            'coupon_snapshot' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function getProductNameSnapshotAttribute(mixed $value): ?string
    {
        $resolved = trim((string) $value);
        if ($resolved !== '') {
            return $resolved;
        }

        $productName = trim((string) ($this->product?->name ?? ''));

        return $productName !== '' ? $productName : null;
    }

    public function getProductTypeSnapshotAttribute(mixed $value): ?string
    {
        $resolved = trim((string) $value);
        if ($resolved !== '') {
            return $resolved;
        }

        $productType = trim((string) ($this->product?->product_type ?? ''));

        return $productType !== '' ? $productType : null;
    }

    public function getDisplayProductNameAttribute(): string
    {
        return $this->product_name_snapshot ?: '未命名商品';
    }

    public function getConfigSnapshotAttribute(mixed $value): array
    {
        $decoded = $this->decodeSnapshotArray($value);

        return $decoded ?? [];
    }

    public function getConfigPricingSnapshotAttribute(mixed $value): ?array
    {
        return $this->decodeSnapshotArray($value);
    }

    public function getCouponSnapshotAttribute(mixed $value): ?array
    {
        return $this->decodeSnapshotArray($value);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function userCoupon()
    {
        return $this->belongsTo(UserCoupon::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function referralReward()
    {
        return $this->hasOne(ReferralReward::class);
    }

    public static function generateOrderNo(?CarbonInterface $time = null, ?string $suffix = null): string
    {
        if ($suffix !== null) {
            return OrderInvoiceNoGenerator::buildOrderNo($time, $suffix);
        }

        return OrderInvoiceNoGenerator::generatePair($time)['order_no'];
    }

    public function scopeOfStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    private function decodeSnapshotArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
