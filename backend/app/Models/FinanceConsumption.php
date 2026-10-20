<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 消费映射：一笔出账从哪个批次扣了多少。
 *
 * restored_amount 会回补批次剩余（钱回来了）；
 * refunded_amount 只登记退款追认，不回补批次。
 */
class FinanceConsumption extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'user_id',
        'lot_id',
        'entry_id',
        'source_type',
        'source_id',
        'amount',
        'restored_amount',
        'refunded_amount',
        'restored_entry_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'user_id' => 'integer',
            'lot_id' => 'integer',
            'entry_id' => 'integer',
            'source_id' => 'integer',
            'amount' => 'decimal:2',
            'restored_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'restored_entry_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(FinanceFundingLot::class, 'lot_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(FinanceLedgerEntry::class, 'entry_id');
    }
}
