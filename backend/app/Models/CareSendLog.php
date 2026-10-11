<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareSendLog extends Model
{
    public const STATUS_PENDING = 0;

    public const STATUS_SENT = 1;

    public const STATUS_FAILED = 2;

    protected $fillable = [
        'care_plan_id', 'run_id', 'user_id', 'cycle_key', 'service_id', 'status', 'error', 'sent_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }
}
