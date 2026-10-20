<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 退款批次证据：new_lot 表示退款款进入账户的新批次，consumption_mark 表示追认的原消费。
 */
class FinanceRefundAllocation extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_NEW_LOT = 'new_lot';

    public const TYPE_CONSUMPTION_MARK = 'consumption_mark';

    protected $fillable = [
        'allocation_key',
        'refund_id',
        'allocation_type',
        'lot_id',
        'consumption_id',
        'amount',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'refund_id' => 'integer',
            'lot_id' => 'integer',
            'consumption_id' => 'integer',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(FinanceRefund::class, 'refund_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(FinanceFundingLot::class, 'lot_id');
    }

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(FinanceConsumption::class, 'consumption_id');
    }
}
