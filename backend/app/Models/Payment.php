<?php

namespace App\Models;

use App\Models\Concerns\NormalizesTraceId;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Payment extends Model
{
    use NormalizesTraceId;

    protected $fillable = [
        'payment_no', 'user_id', 'order_id', 'invoice_id', 'gateway',
        'trade_no', 'amount', 'status', 'callback_raw', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'callback_raw' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function getCallbackRawAttribute(mixed $value): array
    {
        $callbacks = $this->resolveCallbackPayloadFromStructure();
        if ($callbacks !== null) {
            return $callbacks;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function callbacks(): HasMany
    {
        return $this->hasMany(PaymentCallback::class, 'payment_id');
    }

    public static function generatePaymentNo(): string
    {
        return 'PAY'.now()->format('YmdHisv').Str::upper(Str::random(8));
    }

    public function syncPaymentCallbackProjection(): void
    {
        if (! $this->exists || ! Schema::hasTable('payment_callbacks')) {
            return;
        }

        $callbackRaw = $this->legacyCallbackRaw();

        DB::transaction(function () use ($callbackRaw): void {
            DB::table('payment_callbacks')->where('payment_id', (int) $this->id)->delete();

            if ($callbackRaw === []) {
                return;
            }

            DB::table('payment_callbacks')->insert([
                'payment_id' => (int) $this->id,
                'callback_type' => 'payment',
                'gateway_trade_no' => $this->nullableValue($callbackRaw['trade_no'] ?? ($this->trade_no ?? null)),
                'payload_json' => json_encode($callbackRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_verified' => $this->resolveCallbackVerified($callbackRaw),
                'received_at' => $callbackRaw['send_pay_date'] ?? ($this->paid_at ?? $this->updated_at ?? now()),
                'created_at' => $this->created_at ?? now(),
                'updated_at' => $this->updated_at ?? now(),
            ]);

            $refundPayload = is_array($callbackRaw['refund'] ?? null) ? $callbackRaw['refund'] : [];
            if ($refundPayload === []) {
                return;
            }

            DB::table('payment_callbacks')->insert([
                'payment_id' => (int) $this->id,
                'callback_type' => 'refund',
                'gateway_trade_no' => $this->nullableValue($refundPayload['trade_no'] ?? null),
                'payload_json' => json_encode($refundPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'is_verified' => 1,
                'received_at' => $refundPayload['refunded_at'] ?? ($this->updated_at ?? now()),
                'created_at' => $this->created_at ?? now(),
                'updated_at' => $this->updated_at ?? now(),
            ]);
        });
    }

    private function resolveCallbackVerified(array $callbackRaw): int
    {
        if ((string) $this->gateway === 'balance') {
            return 1;
        }

        if (($callbackRaw['code'] ?? null) === '10000') {
            return 1;
        }

        if (trim((string) ($callbackRaw['trade_status'] ?? '')) === 'TRADE_SUCCESS') {
            return 1;
        }

        return 0;
    }

    private function nullableValue(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function resolveCallbackPayloadFromStructure(): ?array
    {
        if (! Schema::hasTable('payment_callbacks') || ! $this->exists) {
            return null;
        }

        if (! $this->relationLoaded('callbacks')) {
            $this->loadMissing('callbacks');
        }

        /** @var Collection<int, PaymentCallback> $callbacks */
        $callbacks = $this->getRelation('callbacks');
        if ($callbacks->isEmpty()) {
            return null;
        }

        $paymentPayload = (array) ($callbacks->firstWhere('callback_type', 'payment')?->payload_json ?? []);
        $refundPayload = (array) ($callbacks->firstWhere('callback_type', 'refund')?->payload_json ?? []);

        if ($paymentPayload === [] && $refundPayload === []) {
            return null;
        }

        if ($refundPayload !== []) {
            $paymentPayload['refund'] = $refundPayload;
        }

        return $paymentPayload;
    }

    private function legacyCallbackRaw(): array
    {
        $raw = $this->getRawOriginal('callback_raw');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }
}
