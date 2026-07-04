<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use SoftDeletes;

    /**
     * products 表列清单缓存，按连接名分组，避免重复 getColumnListing 调用。
     *
     * @var array<string, list<string>>
     */
    protected static array $productColumnsCache = [];

    private const BILLING_CYCLE_MONTHS = [
        'monthly' => 1,
        'quarterly' => 3,
        'semiannually' => 6,
        'annually' => 12,
    ];

    protected $fillable = [
        'first_product_group_id', 'second_product_group_id', 'third_product_group_id',
        'service_type_code',
        'name', 'product_type', 'type',
        'custom_display_name', 'remark',
        'pricing',
        'setup_fee', 'config_options', 'purchase_requires', 'stock', 'status',
        'sort_order', 'auto_setup',
        'supplier_product_name',
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
            'first_product_group_id' => 'integer',
            'second_product_group_id' => 'integer',
            'third_product_group_id' => 'integer',
        ];
    }

    protected static function booted(): void {}

    public function getProductTypeAttribute(mixed $value): string
    {
        $normalized = trim((string) ($value ?? ''));
        if ($normalized !== '') {
            return $normalized;
        }

        $serviceTypeCode = trim((string) ($this->attributes['service_type_code'] ?? ''));
        if ($serviceTypeCode !== '') {
            return $serviceTypeCode;
        }

        if ($this->relationLoaded('firstProductGroup') && $this->firstProductGroup instanceof FirstProductGroup) {
            return trim((string) $this->firstProductGroup->code);
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

    public function getSupplierProductNameAttribute(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    public function getRemarkAttribute(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    public function getCustomDisplayNameAttribute(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    public function getSupplierAttribute(): ?Supplier
    {
        if ((int) ($this->attributes['id'] ?? 0) <= 0) {
            return null;
        }

        try {
            return app(PluginBindingResolver::class)->supplierForProduct($this);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setNameAttribute(mixed $value): void
    {
        unset($this->attributes['name']);
    }

    public function setSupplierProductNameAttribute(mixed $value): void
    {
        $normalized = trim((string) $value);
        $this->attributes['supplier_product_name'] = $normalized !== '' ? $normalized : null;
    }

    public function setCustomDisplayNameAttribute(mixed $value): void
    {
        $normalized = trim((string) $value);
        $this->attributes['custom_display_name'] = $normalized !== '' ? $normalized : null;
    }

    public function setRemarkAttribute(mixed $value): void
    {
        $normalized = trim((string) $value);
        $this->attributes['remark'] = $normalized !== '' ? $normalized : null;
    }

    public function getNameAttribute(mixed $value): string
    {
        $customDisplayName = $this->getCustomDisplayNameAttribute($this->attributes['custom_display_name'] ?? null);
        if ($customDisplayName !== null) {
            return $customDisplayName;
        }

        $normalized = trim((string) $value);
        if ($normalized !== '') {
            return $normalized;
        }

        try {
            $display = (new ProductDisplayNameResolver)->resolveForProduct($this);

            return trim((string) ($display['product_display_name'] ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }

    public function firstProductGroup(): BelongsTo
    {
        return $this->belongsTo(FirstProductGroup::class, 'first_product_group_id');
    }

    public function secondProductGroup(): BelongsTo
    {
        return $this->belongsTo(SecondProductGroup::class, 'second_product_group_id');
    }

    public function thirdProductGroup(): BelongsTo
    {
        return $this->belongsTo(ThirdProductGroup::class, 'third_product_group_id');
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class, 'id', 'id')->whereRaw('1 = 0');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function upstreamBindings(): HasMany
    {
        return $this->hasMany(ProductUpstreamBinding::class, 'product_id');
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

    public function getTypeAttribute(): ?string
    {
        $value = $this->getProductTypeAttribute($this->attributes['product_type'] ?? null);

        return $value !== '' ? $value : null;
    }

    public function setTypeAttribute(?string $value): void
    {
        $this->attributes['product_type'] = $value;
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

    public static function optionalSelectColumns(array $columns): array
    {
        $connectionName = (new static)->getConnectionName() ?: config('database.default', 'default');

        try {
            $available = self::cachedProductColumns($connectionName);

            return array_values(array_filter(
                $columns,
                static fn (string $column): bool => in_array(strtolower($column), $available, true)
            ));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 按连接缓存 products 表的列清单（小写），避免在单次请求/测试中
     * 重复触发 getColumnListing（SQLite 下为 pragma_table_xinfo）造成 N+1 表结构查询。
     */
    protected static function cachedProductColumns(string $connectionName): array
    {
        if (isset(self::$productColumnsCache[$connectionName])) {
            return self::$productColumnsCache[$connectionName];
        }

        $columns = array_map('strtolower', DB::connection($connectionName)->getSchemaBuilder()->getColumnListing('products'));

        return self::$productColumnsCache[$connectionName] = $columns;
    }

    public static function buildIdcMirrorPayload(self $product, ?string $slug = null): array
    {
        $connection = $product->getConnection();
        $schema = $connection->getSchemaBuilder();
        $payload = [];

        $encodeJson = static fn (array $value): string => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $setIfColumnExists = static function (string $column, mixed $value) use (&$payload, $schema): void {
            if ($schema->hasColumn('products', $column)) {
                $payload[$column] = $value;
            }
        };

        $setIfColumnExists('product_type', (string) ($product->product_type ?: 'other'));
        $setIfColumnExists('name', (string) $product->name);
        $setIfColumnExists('custom_display_name', $product->custom_display_name);
        $setIfColumnExists('slug', $slug ?: 'test-product-'.(int) $product->id);
        $setIfColumnExists('summary', null);
        $setIfColumnExists('remark', $product->remark);
        $setIfColumnExists('pricing', $encodeJson((array) ($product->pricing ?? [])));
        $setIfColumnExists('setup_fee', number_format((float) ($product->setup_fee ?? 0), 2, '.', ''));
        $setIfColumnExists('config_options', $encodeJson((array) ($product->config_options ?? [])));
        $setIfColumnExists('purchase_requires', $encodeJson((array) ($product->purchase_requires ?? [])));
        $setIfColumnExists('purchase_requires_json', $encodeJson((array) ($product->purchase_requires ?? [])));
        $setIfColumnExists('stock', (int) ($product->stock ?? -1));
        $setIfColumnExists('status', (int) ($product->status ?? 1));
        $setIfColumnExists('sort_order', (int) ($product->sort_order ?? 0));
        $setIfColumnExists('auto_setup', (int) ($product->auto_setup ?? 0));
        $setIfColumnExists('supplier_product_name', $product->supplier_product_name);
        $setIfColumnExists('deleted_at', null);
        $setIfColumnExists('created_at', $product->created_at ?? now());
        $setIfColumnExists('updated_at', $product->updated_at ?? now());

        return $payload;
    }
}
