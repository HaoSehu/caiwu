<?php

namespace App\Models;

use App\Constants\PaymentGatewayCode;
use App\Models\Concerns\NormalizesTraceId;
use App\Support\VersionedJson;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Payment extends Model
{
    use NormalizesTraceId;

    protected $fillable = [
        'payment_no', 'user_id', 'order_id', 'invoice_id', 'gateway',
        'trade_no', 'amount', 'status', 'callback_raw', 'paid_at',
        'trace_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'callback_raw' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $gateway = trim((string) $payment->gateway);

            if (! PaymentGatewayCode::isThirdParty($gateway)) {
                throw new InvalidArgumentException('Payment 仅允许记录第三方真实资金流入，余额、手工、免费流程请使用账单和账户流水表达。');
            }
        });
    }

    public function getCallbackRawAttribute(mixed $value): array
    {
        $decoded = VersionedJson::decodeToArray($value);
        if (($this->isDirty('callback_raw') || $this->wasChanged('callback_raw')) && $decoded !== null) {
            return VersionedJson::paymentCallback($decoded, 'payment');
        }

        $callbacks = $this->resolveCallbackPayloadFromStructure();
        if ($callbacks !== null) {
            return $callbacks;
        }

        return $decoded === null ? [] : VersionedJson::paymentCallback($decoded, 'payment');
    }

    public function setCallbackRawAttribute(mixed $value): void
    {
        $payload = VersionedJson::decodeToArray($value);
        $this->attributes['callback_raw'] = $payload === null
            ? null
            : json_encode(VersionedJson::paymentCallback($payload, 'payment'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function callbackPayload(string $callbackType = 'payment'): array
    {
        if (! Schema::hasTable('payment_callbacks') || ! $this->exists) {
            return [];
        }

        if (! $this->relationLoaded('callbacks')) {
            $this->loadMissing('callbacks');
        }

        /** @var Collection<int, PaymentCallback> $callbacks */
        $callbacks = $this->getRelation('callbacks');
        $payload = $callbacks->firstWhere('callback_type', $callbackType)?->payload_json;

        return is_array($payload)
            ? VersionedJson::paymentCallback($payload, $callbackType)
            : [];
    }

    public function refundCallbackPayload(): array
    {
        return $this->callbackPayload('refund');
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
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $no = 'PAY'.now()->format('YmdHisv').Str::upper(Str::random(8));

            if (! static::query()->where('payment_no', $no)->exists()) {
                return $no;
            }
        }

        return 'PAY'.now()->format('YmdHisv').Str::upper(Str::random(12));
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

        $paymentPayload = VersionedJson::paymentCallback(
            $callbacks->firstWhere('callback_type', 'payment')?->payload_json ?? [],
            'payment'
        );
        $refundPayload = VersionedJson::paymentCallback(
            $callbacks->firstWhere('callback_type', 'refund')?->payload_json ?? [],
            'refund'
        );

        if ($callbacks->firstWhere('callback_type', 'payment') === null && $callbacks->firstWhere('callback_type', 'refund') === null) {
            return null;
        }

        if ($refundPayload !== []) {
            $paymentPayload['refund'] = $refundPayload;
        }

        return $paymentPayload;
    }
}
