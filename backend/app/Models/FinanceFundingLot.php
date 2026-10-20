<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 资金批次：一笔入账对应的可核销来源。amount 建后不变，remaining_amount 随消费/恢复调整。
 */
class FinanceFundingLot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'lot_no',
        'account_id',
        'user_id',
        'entry_id',
        'source_type',
        'source_id',
        'origin_type',
        'origin_id',
        'amount',
        'remaining_amount',
        'data_quality',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'user_id' => 'integer',
            'entry_id' => 'integer',
            'source_id' => 'integer',
            'origin_id' => 'integer',
            'amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(FinanceLedgerEntry::class, 'entry_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(FinanceConsumption::class, 'lot_id');
    }
}
