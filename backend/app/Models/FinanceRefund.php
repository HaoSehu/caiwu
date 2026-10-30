<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        // 原支付单的插件与通道快照（P-08）：退款链路不再回查 payments 也能定位具体插件。
        'plugin_id', 'plugin_key', 'plugin_name', 'gateway_key', 'payment_type',
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
            'plugin_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => 'integer',
            'funds_moved' => 'boolean',
            'delivery_evidence' => 'array',
            'operator_id' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * 已完成退款累计的统一口径：真实退款（funds_moved=1）与历史纯标记（funds_moved=0）
     * 同表记录、都是「已确认退过」的事实，累计一律计入。只算 funds_moved=1 会让
     * 「先标记 30、再真退 100」叠出 130 的超额退款，报表净额也会少扣。
     *
     * 可退额、账单投影、收入统计必须共用本口径，不得各自写 funds_moved 条件。
     *
     * @return Builder<static>
     */
    public static function completedTotalsQuery(): Builder
    {
        return static::query()
            ->select('invoice_id', DB::raw('SUM(amount) as refunded_total'))
            ->where('status', self::STATUS_COMPLETED)
            ->groupBy('invoice_id');
    }

    /**
     * 单张账单的已完成退款累计金额（元，保留两位）。
     */
    public static function completedAmountForInvoice(int $invoiceId): float
    {
        return round((float) static::query()
            ->where('invoice_id', $invoiceId)
            ->where('status', self::STATUS_COMPLETED)
            ->sum('amount'), 2);
    }

    /**
     * 收入净额子查询片段：给定账单列（含表前缀），返回已完成退款累计的 SQL。
     * 供无法使用查询构造器的 selectRaw 聚合使用。
     *
     * 安全约束：$invoiceColumn 会直接拼进 SQL，只允许传入本仓库内的字面量列名
     * （如 'inv.id'），绝不接受请求参数、配置项或任何外部输入。
     */
    public static function completedRefundSumSql(string $invoiceColumn): string
    {
        return '(SELECT SUM(finance_refunds.amount) FROM finance_refunds WHERE finance_refunds.invoice_id = '.$invoiceColumn.' AND finance_refunds.status = '.self::STATUS_COMPLETED.')';
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
