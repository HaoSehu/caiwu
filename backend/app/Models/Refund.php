<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\NormalizesTraceId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use NormalizesTraceId;

    protected $table = 'refunds';

    protected $connection = 'idc';

    protected $fillable = [
        'refund_no',
        'payment_id',
        'invoice_id',
        'user_id',
        'amount',
        'status',
        'reason',
        'gateway_refund_no',
        'refunded_at',
        'trace_id',
        'remark',
        'operator',
    ];

    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'invoice_id' => 'integer',
            'user_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
