<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 财务对账差异：无法自动核平的存量问题，一条对象一条记录。
 */
class FinanceReconciliationDiff extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_IGNORED = 'ignored';

    protected $fillable = [
        'check_type',
        'scope_type',
        'scope_id',
        'expected_value',
        'actual_value',
        'diff_value',
        'details',
        'status',
        'detected_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'scope_id' => 'integer',
            'expected_value' => 'decimal:2',
            'actual_value' => 'decimal:2',
            'diff_value' => 'decimal:2',
            'details' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
