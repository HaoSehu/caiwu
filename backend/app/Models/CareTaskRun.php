<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareTaskRun extends Model
{
    public const STATUS_PENDING = 0;

    public const STATUS_SUCCESS = 1;

    public const STATUS_PARTIAL_FAILED = 2;

    public const STATUS_FAILED = 3;

    public const STATUS_SKIPPED = 4;

    protected $fillable = [
        'care_plan_id', 'planned_date', 'status', 'total_count', 'sent_count',
        'failed_count', 'skipped_count', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'planned_date' => 'date:Y-m-d',
        'status' => 'integer',
        'total_count' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
        'skipped_count' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }
}
