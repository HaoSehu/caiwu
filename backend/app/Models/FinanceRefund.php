<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 退款单：唯一通道为「退款到余额」，历史纯标记行以 funds_moved=0 只读保留。
 */
class FinanceRefund extends Model
{
    /** 处理中。 */
    public const STATUS_PROCESSING = 0;

    /** 已完成。 */
    public const STATUS_COMPLETED = 1;

    /** 失败。 */
    public const STATUS_FAILED = 2;

    /** 原生（新通道产生）。 */
    public const ORIGIN_NATIVE = 'native';

    /** 历史余额退款。 */
    public const ORIGIN_LEGACY_BALANCE = 'legacy_balance';

    /** 历史纯标记（未动资金）。 */
    public const ORIGIN_LEGACY_MANUAL_MARK = 'legacy_manual_mark';

    protected $fillable = [
        'refund_no',
        'user_id',
        'invoice_id',
        'refund_invoice_id',
        'payment_id',
        'amount',
        'currency',
        'status',
        'funds_moved',
        'data_origin',
        'reason',
        'delivery_evidence',
        'operator_type',
        'operator_id',
        'operator_name',
        'trace_id',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'invoice_id' => 'integer',
            'refund_invoice_id' => 'integer',
            'payment_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => 'integer',
            'funds_moved' => 'boolean',
            'delivery_evidence' => 'array',
            'operator_id' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function refundInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'refund_invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FinanceRefundAllocation::class, 'refund_id');
    }
}
