<?php

namespace App\Models;

use App\Models\Concerns\NormalizesTraceId;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\OrderInvoiceNoGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Invoice extends Model
{
    use NormalizesTraceId;

    protected $fillable = [
        'invoice_no', 'user_id', 'order_id', 'type',
        'product_id', 'product_spec_snapshot', 'product_type_snapshot',
        'service_id',
        'coupon_id', 'user_coupon_id', 'coupon_code',
        'amount', 'discount', 'paid_amount',
        'billing_cycle', 'quantity',
        'config_snapshot', 'config_pricing_snapshot', 'coupon_snapshot',
        'status', 'due_date', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'config_snapshot' => 'array',
            'config_pricing_snapshot' => 'array',
            'coupon_snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id');
    }

    public function getProductSpecSnapshotAttribute(mixed $value): ?string
    {
        $resolved = trim((string) $value);
        if ($resolved !== '') {
            return $resolved;
        }

        $legacySnapshot = trim((string) ($this->attributes['product_name_snapshot'] ?? ''));
        if ($legacySnapshot !== '') {
            return $legacySnapshot;
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

        if ($this->hasPhysicalColumn('product_name_snapshot')) {
            $this->attributes['product_name_snapshot'] = $normalized !== '' ? $normalized : null;

            return;
        }

        unset($this->attributes['product_name_snapshot']);
    }

    public function getDisplayProductNameAttribute(): string
    {
        $snapshot = (string) ($this->product_spec_snapshot ?? '');
        if ($snapshot !== '') {
            return $snapshot;
        }

        return '';
    }

    public static function generateInvoiceNo(?CarbonInterface $time = null, ?string $suffix = null): string
    {
        if ($suffix !== null) {
            return OrderInvoiceNoGenerator::buildInvoiceNo($time, $suffix);
        }

        return OrderInvoiceNoGenerator::generatePair($time)['invoice_no'];
    }

    public static function generateInvoiceNoFromOrderNo(string $orderNo): string
    {
        return OrderInvoiceNoGenerator::deriveInvoiceNoFromOrderNo($orderNo) ?? self::generateInvoiceNo();
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 0);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 3);
    }

    public function syncInvoiceItemProjection(): void
    {
        if (! $this->exists || ! Schema::hasTable('invoice_items')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('invoice_items')->where('invoice_id', (int) $this->id)->delete();

            $itemName = $this->display_product_name;
            if ($itemName === '') {
                $this->loadMissing('order.product:id,product_type,product_group_id,config_options,purchase_requires');
                $itemName = trim((string) ($this->order?->display_product_name ?? ''));
            }
            $quantity = max((int) ($this->quantity ?? $this->order?->quantity ?? 1), 1);
            $grossAmount = (float) ($this->amount ?? 0) + (float) ($this->discount ?? 0);
            $discountAmount = (float) ($this->discount ?? $this->order?->discount ?? 0);
            $unitPrice = $quantity > 0 ? $grossAmount / $quantity : $grossAmount;

            DB::table('invoice_items')->insert([
                'invoice_id' => (int) $this->id,
                'item_name' => $itemName !== '' ? $itemName : '账单项目',
                'item_type' => trim((string) ($this->type ?? 'normal')),
                'quantity' => $quantity,
                'unit_price' => $this->normalizeDecimal($unitPrice),
                'discount_amount' => $this->normalizeDecimal($discountAmount),
                'line_amount' => $this->normalizeDecimal($this->amount ?? 0),
                'meta_json' => $this->encodeJson([
                    'invoice_no' => $this->invoice_no,
                    'order_no' => $this->order?->order_no,
                    'product_name' => $itemName !== '' ? $itemName : null,
                    'quantity' => $quantity,
                ]),
                'created_at' => $this->created_at ?? now(),
                'updated_at' => $this->updated_at ?? now(),
            ]);
        });
    }

    private function normalizeDecimal(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function encodeJson(?array $value): ?string
    {
        if (! is_array($value) || $value === []) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
