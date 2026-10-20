<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 现金账户：余额真源，替代 user_accounts.cash_balance。
 *
 * 只允许通过 FinanceAccountService 写入；本模型不提供业务方法，避免绕过批次与台账。
 */
class FinanceAccount extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
        'available_amount',
        'frozen_amount',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'available_amount' => 'decimal:2',
            'frozen_amount' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FinanceLedgerEntry::class, 'account_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(FinanceFundingLot::class, 'account_id');
    }
}
