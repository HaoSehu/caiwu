<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ProductCatalog\ProductDisplayNameResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    private const BILLING_CYCLE_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannually' => 6,
        'annually' => 12,
    ];

    protected $fillable = [
        'product_group_id', 'category_id', 'name', 'product_type', 'type',
        'meta_title', 'meta_description', 'meta_keywords', 'remark',
        'pricing',
        'setup_fee', 'config_options', 'purchase_requires', 'stock', 'status',
        'sort_order', 'provision_module', 'auto_setup',
        'supplier_id', 'supplier_product_id', 'supplier_product_name',
    ];

    protected function casts(): array
    {
        return [
            'pricing' => 'array',
            'config_options' => 'array',
            'purchase_requires' => 'array',
            'setup_fee' => 'decimal:2',
            'stock' => 'integer',
            'status' => 'integer',
            'sort_order' => 'integer',
            'auto_setup' => 'integer',
            'category_id' => 'integer',
            'product_group_id' => 'integer',
            'supplier_id' => 'integer',
            'supplier_product_id' => 'integer',
        ];
    }

    protected static function booted(): void {}

    public function getProductTypeAttribute(mixed $value): string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized !== '') {
            return $normalized;
        }

        $category = $this->resolveCategoryModel();
        if ($category) {
            $typeCode = trim((string) ($category->product_type ?? ''));
            if ($typeCode !== '') {
                return $typeCode;
            }
        }

        return '';
    }

    public function getPricingAttribute(mixed $value): array
    {
        return $this->normalizeLegacyPricing($this->decodeJsonArrayAttribute($value));
    }

    public function getConfigOptionsAttribute(mixed $value): array
    {
        return $this->decodeJsonArrayAttribute($value);
    }

    public function getPurchaseRequiresAttribute(mixed $value): array
    {
        return $this->decodeJsonArrayAttribute($value);
    }

    public function getSupplierProductIdAttribute(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    public function getSupplierProductNameAttribute(mixed $value): ?string
    {
        if (! $this->hasPhysicalColumn('supplier_product_name')) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    public function getRemarkAttribute(mixed $value): ?string
    {
        if (! $this->hasPhysicalColumn('remark')) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    public function setNameAttribute(mixed $value): void
    {
        if (! $this->hasPhysicalColumn('name')) {
            unset($this->attributes['name']);

            return;
        }

        $this->attributes['name'] = trim((string) $value);
    }

    public function setSupplierProductNameAttribute(mixed $value): void
    {
        if (! $this->hasPhysicalColumn('supplier_product_name')) {
            unset($this->attributes['supplier_product_name']);

            return;
        }

        $normalized = trim((string) $value);
        $this->attributes['supplier_product_name'] = $normalized !== '' ? $normalized : null;
    }

    public function setRemarkAttribute(mixed $value): void
    {
        if (! $this->hasPhysicalColumn('remark')) {
            unset($this->attributes['remark']);

            return;
        }

        $normalized = trim((string) $value);
        $this->attributes['remark'] = $normalized !== '' ? $normalized : null;
    }

    public function getNameAttribute(mixed $value): string
    {
        if ($this->hasPhysicalColumn('name')) {
            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        try {
            $display = (new ProductDisplayNameResolver)->resolveForProduct($this);

            return trim((string) ($display['product_spec_display'] ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }

    public function getCategoryIdAttribute(mixed $value): ?int
    {
        if ($value !== null && $value !== '') {
            return (int) $value;
        }

        $productGroupId = $this->attributes['product_group_id'] ?? null;

        return $productGroupId === null ? null : (int) $productGroupId;
    }

    public function setCategoryIdAttribute(mixed $value): void
    {
        $this->attributes['product_group_id'] = $value === null || $value === '' ? null : (int) $value;
        unset($this->attributes['category_id']);
    }

    public function categoryMapping(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_group_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function scopeOnSale($query)
    {
        return $query->where('products.status', 1);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('product_type', $type);
    }

    public function getPriceByBillingCycle(string $cycle): float
    {
        $pricing = $this->pricing;

        if (array_key_exists($cycle, $pricing)) {
            return round((float) ($pricing[$cycle] ?? 0), 2);
        }

        $monthly = $pricing['monthly'] ?? null;
        $months = self::BILLING_CYCLE_MONTHS[$cycle] ?? 0;

        if ($monthly === null || $months <= 0 || ! is_numeric($monthly)) {
            return 0;
        }

        return round((float) $monthly * $months, 2);
    }

    public function getGroupIdAttribute(): ?int
    {
        $category = $this->resolveCategoryModel();
        if ($category) {
            return (int) $category->id;
        }

        return null;
    }

    public function setGroupIdAttribute($value): void {}

    public function getTypeAttribute(): ?string
    {
        $value = $this->getProductTypeAttribute($this->attributes['product_type'] ?? null);

        return $value !== '' ? $value : null;
    }

    public function setTypeAttribute(?string $value): void
    {
        $this->attributes['product_type'] = $value;
    }

    private function resolveCategoryModel(): ?ProductCategory
    {
        if (! $this->exists) {
            return null;
        }

        if (! $this->relationLoaded('categoryMapping')) {
            $this->loadMissing('categoryMapping');
        }

        /** @var ProductCategory|null $category */
        $category = $this->getRelation('categoryMapping');

        return $category;
    }

    private function normalizeLegacyPricing(array $pricing): array
    {
        return collect($pricing)
            ->filter(fn ($amount, $cycle) => is_string($cycle) && $cycle !== '')
            ->mapWithKeys(fn ($amount, $cycle) => [
                (string) $cycle => is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : '0.00',
            ])
            ->all();
    }

    private function decodeJsonArrayAttribute(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function hasPhysicalColumn(string $column): bool
    {
        try {
            return $this->getConnection()->getSchemaBuilder()->hasColumn($this->getTable(), $column);
        } catch (\Throwable) {
            return false;
        }
    }
}
