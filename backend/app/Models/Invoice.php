<?php

namespace App\Models;

use App\Support\OrderInvoiceNoGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Invoice extends Model
{
    protected $fillable = [
        'invoice_no', 'user_id', 'order_id', 'type',
        'amount', 'paid_amount', 'status', 'due_date', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date'    => 'date',
            'paid_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $invoice): void {
            $invoice->syncInvoiceItemProjection();
        });
    }

    public function user()  { return $this->belongsTo(User::class); }
    public function order() { return $this->belongsTo(Order::class); }
    public function payments() { return $this->hasMany(Payment::class); }
    public function items() { return $this->hasMany(InvoiceItem::class, 'invoice_id'); }

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

    public function scopeUnpaid($query)  { return $query->where('status', 0); }
    public function scopeOverdue($query) { return $query->where('status', 3); }

    public function syncInvoiceItemProjection(): void
    {
        if (! $this->exists || ! Schema::hasTable('invoice_items')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('invoice_items')->where('invoice_id', (int) $this->id)->delete();

            $this->loadMissing('order.product:id,name');
            $itemName = trim((string) ($this->order?->display_product_name ?? ''));
            $quantity = max((int) ($this->order?->quantity ?? 1), 1);
            $grossAmount = (float) ($this->order?->amount ?? $this->amount ?? 0);
            $discountAmount = (float) ($this->order?->discount ?? 0);
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
}
