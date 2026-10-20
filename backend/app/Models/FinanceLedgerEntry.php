<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 不可变资金台账行。只插不改：模型禁用 updated_at，服务层不提供更新方法。
 */
class FinanceLedgerEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entry_no',
        'account_id',
        'user_id',
        'account_type',
        'event_type',
        'change_amount',
        'balance_after',
        'currency',
        'funding_lot_id',
        'source_type',
        'source_id',
        'origin_type',
        'origin_id',
        'operator_type',
        'operator_id',
        'operator_name',
        'remark',
        'trace_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'user_id' => 'integer',
            'change_amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'funding_lot_id' => 'integer',
            'source_id' => 'integer',
            'origin_id' => 'integer',
            'operator_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** 入账行创建的资金批次（1:1）。 */
    public function lot(): HasOne
    {
        return $this->hasOne(FinanceFundingLot::class, 'entry_id');
    }
}
