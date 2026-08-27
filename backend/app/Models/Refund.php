<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\EnsuresTraceId;
use App\Models\Concerns\NormalizesTraceId;
use App\Support\OrderInvoiceNoGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Refund extends Model
{
    use EnsuresTraceId, NormalizesTraceId;

    public const STATUS_COMPLETED = 1;

    protected $table = 'refunds';

    protected $fillable = [
        'refund_no',
        'payment_id',
        'invoice_id',
        'refund_invoice_id',
        'user_id',
        'amount',
        'status',
        'refund_method',
        'currency',
        'reason',
        'gateway_refund_no',
        'operator_type',
        'operator_id',
        'operator_name',
        'refunded_at',
        'trace_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'invoice_id' => 'integer',
            'refund_invoice_id' => 'integer',
            'user_id' => 'integer',
            'operator_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public static function generateRefundNo(): string
    {
        // RFD + 14 位时间戳 + 8 位大写随机串，格式与既有生产数据一致。
        return OrderInvoiceNoGenerator::generateSerialNumber('RFD');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function refundInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'refund_invoice_id');
    }

    public function rechargeRecords(): HasMany
    {
        return $this->hasMany(RechargeRecord::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
