<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalanceSnapshot extends Model
{
    protected $table = 'account_balance_snapshots';

    protected $connection = 'idc';

    protected $fillable = [
        'user_id',
        'account_type',
        'available_balance',
        'frozen_balance',
        'snapshot_date',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'available_balance' => 'decimal:2',
            'frozen_balance' => 'decimal:2',
            'snapshot_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
