<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use RuntimeException;

/**
 * 不可变资金台账行。只插不改：模型禁用 updated_at，服务层不提供更新方法；
 * booted 守卫把「不可变」从服务层纪律下沉为模型层兜底。
 */
class FinanceLedgerEntry extends Model
{
    public $timestamps = false;

    /** 纵深防御：台账 meta 可能含操作人/渠道明细，默认不进序列化。 */
    protected $hidden = ['meta'];

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

    protected static function booted(): void
    {
        // 资金台账行不可变：金额/余额/账户/事件类型/流水号任何写入一律拒绝。
        // 其余列保留既有合法回填（如 FundingLotAllocator 写 funding_lot_id）。
        static::updating(function (FinanceLedgerEntry $entry): void {
            foreach (['change_amount', 'balance_after', 'account_id', 'event_type', 'entry_no'] as $attribute) {
                if ($entry->isDirty($attribute)) {
                    throw new RuntimeException('资金台账行不可修改');
                }
            }
        });
    }

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
