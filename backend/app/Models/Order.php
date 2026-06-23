<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\OrderInvoiceNoGenerator;
use App\Support\VersionedJson;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const PROJECTION_TYPE_PROVISIONING = 'provisioning';

    protected $fillable = [
        'order_no',
        'projection_type',
        'user_id',
        'product_id',
        'product_spec_snapshot',
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

    public function getProductSpecSnapshotAttribute(mixed $value): ?string
    {
        $resolved = trim((string) $value);
        if ($resolved !== '') {
            return $resolved;
        }

        return null;
    }

    public function getProductNameSnapshotAttribute(mixed $value): ?string
    {
        $resolved = trim((string) ($this->product_spec_snapshot ?? $value));
        if ($resolved !== '') {
            return $resolved;
        }

        $product = $this->product;
        if ($product instanceof Product) {
            $displayName = trim((string) ((new ProductDisplayNameResolver)->resolveForProduct($product)['product_display_name'] ?? ''));

            return $displayName !== '' ? $displayName : null;
        }

        return null;
    }

    public function setProductNameSnapshotAttribute(mixed $value): void
    {
        $normalized = trim((string) $value);

        if ($normalized !== '' && trim((string) ($this->attributes['product_spec_snapshot'] ?? '')) === '') {
            $this->attributes['product_spec_snapshot'] = $normalized;
        }

        unset($this->attributes['product_name_snapshot']);
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
        $resolved = trim((string) ($this->product_name_snapshot ?? ''));

        return $resolved !== '' ? $resolved : '未配置规格';
    }

    public function getConfigSnapshotAttribute(mixed $value): array
    {
        $decoded = VersionedJson::tradeSnapshot($value, 'order.config_snapshot');

        return $decoded ?? [];
    }

    public function getConfigPricingSnapshotAttribute(mixed $value): ?array
    {
        return VersionedJson::tradeSnapshot($value, 'order.config_pricing_snapshot');
    }

    public function getCouponSnapshotAttribute(mixed $value): ?array
    {
        return VersionedJson::tradeSnapshot($value, 'order.coupon_snapshot');
    }

    public function setConfigSnapshotAttribute(mixed $value): void
    {
        $this->attributes['config_snapshot'] = $this->encodeSnapshotArray(
            VersionedJson::tradeSnapshot($value, 'order.config_snapshot')
        );
    }

    public function setConfigPricingSnapshotAttribute(mixed $value): void
    {
        $this->attributes['config_pricing_snapshot'] = $this->encodeSnapshotArray(
            VersionedJson::tradeSnapshot($value, 'order.config_pricing_snapshot')
        );
    }

    public function setCouponSnapshotAttribute(mixed $value): void
    {
        $this->attributes['coupon_snapshot'] = $this->encodeSnapshotArray(
            VersionedJson::tradeSnapshot($value, 'order.coupon_snapshot')
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function userCoupon(): BelongsTo
    {
        return $this->belongsTo(UserCoupon::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function referralReward(): HasOne
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

    private function encodeSnapshotArray(?array $value): ?string
    {
        return is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;
    }
}
