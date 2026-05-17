<?php

namespace App\Models;

use App\Constants\FinanceLedgerEventType;
use App\Support\UserBalanceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BalanceLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_type',
        'change_amount',
        'balance_after',
        'reference_id',
        'remark',
        'type',
        'amount',
        'balance',
        'related_id',
    ];

    protected function casts(): array
    {
        return [
            'change_amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'reference_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $log): void {
            $log->syncAccountTransactionProjection();
            UserBalanceCache::forget((int) $log->user_id);
        });

        static::deleted(function (self $log): void {
            UserBalanceCache::forget((int) $log->user_id);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getTypeAttribute(): ?string
    {
        return $this->attributes['event_type'] ?? null;
    }

    public function setTypeAttribute(?string $value): void
    {
        $this->attributes['event_type'] = $value;
    }

    public function getAmountAttribute(): ?string
    {
        return $this->attributes['change_amount'] ?? null;
    }

    public function setAmountAttribute($value): void
    {
        $this->attributes['change_amount'] = $value;
    }

    public function getBalanceAttribute(): ?string
    {
        return $this->attributes['balance_after'] ?? null;
    }

    public function setBalanceAttribute($value): void
    {
        $this->attributes['balance_after'] = $value;
    }

    public function getRelatedIdAttribute(): ?int
    {
        $value = $this->attributes['reference_id'] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function setRelatedIdAttribute($value): void
    {
        $this->attributes['reference_id'] = $value;
    }

    public function syncAccountTransactionProjection(): void
    {
        if (! $this->exists || ! Schema::hasTable('account_transactions')) {
            return;
        }

        DB::table('account_transactions')->updateOrInsert(
            [
                'origin_type' => 'balance_log',
                'origin_id' => (int) $this->id,
            ],
            [
                'user_id' => (int) $this->user_id,
                'account_type' => 'cash',
                'event_type' => trim((string) $this->event_type),
                'change_amount' => $this->normalizeDecimal($this->change_amount),
                'balance_after' => $this->normalizeDecimal($this->balance_after),
                'source_type' => $this->resolveSourceType((string) $this->event_type),
                'source_id' => $this->reference_id ? (int) $this->reference_id : null,
                'remark' => trim((string) ($this->remark ?? '')) ?: null,
                'operator' => null,
                'trace_id' => null,
                'created_at' => $this->created_at ?? now(),
                'updated_at' => $this->created_at ?? now(),
            ]
        );
    }

    private function resolveSourceType(string $eventType): ?string
    {
        return match (FinanceLedgerEventType::normalize(trim($eventType))) {
            FinanceLedgerEventType::RECHARGE => 'payment',
            FinanceLedgerEventType::MANUAL_RECHARGE,
            FinanceLedgerEventType::MANUAL_DEDUCTION,
            FinanceLedgerEventType::SYSTEM_ADJUSTMENT => 'manual_adjustment',
            FinanceLedgerEventType::INVOICE_PAYMENT,
            FinanceLedgerEventType::INVOICE_REFUND => 'invoice',
            FinanceLedgerEventType::REFERRAL_CREDIT_CASH => 'referral_withdrawal',
            default => null,
        };
    }

    private function normalizeDecimal(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }
}
