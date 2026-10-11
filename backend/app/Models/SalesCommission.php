<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\EnsuresTraceId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * M5 提成计提记录：计提粒度=账单（invoice_id 唯一约束防重）。
 * 资金红线：计提/结算全程不动用户余额、不写 AccountTransaction、不改 Payment。
 *
 * @property int $id
 * @property int $admin_user_id
 * @property int $user_id
 * @property int|null $order_id
 * @property int $invoice_id
 * @property int|null $plan_id
 * @property array|null $plan_snapshot
 * @property float $base_amount
 * @property float $rate
 * @property float $commission_amount
 * @property int $status
 * @property int|null $settlement_id
 * @property Carbon|null $settled_at
 * @property string|null $trace_id
 * @property AdminUser|null $adminUser
 * @property User|null $user
 * @property Order|null $order
 * @property Invoice|null $invoice
 * @property SalesCommissionPlan|null $plan
 * @property SalesCommissionSettlement|null $settlement
 */
class SalesCommission extends Model
{
    use EnsuresTraceId;

    public const STATUS_PENDING = 0;

    public const STATUS_IN_BATCH = 1;

    public const STATUS_SETTLED = 2;

    public const STATUS_CANCELLED = 3;

    protected $fillable = [
        'admin_user_id',
        'user_id',
        'order_id',
        'invoice_id',
        'plan_id',
        'plan_snapshot',
        'base_amount',
        'rate',
        'commission_amount',
        'status',
        'settlement_id',
        'settled_at',
        'trace_id',
    ];

    protected function casts(): array
    {
        return [
            'admin_user_id' => 'integer',
            'user_id' => 'integer',
            'order_id' => 'integer',
            'invoice_id' => 'integer',
            'plan_id' => 'integer',
            'plan_snapshot' => 'array',
            'base_amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'status' => 'integer',
            'settlement_id' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SalesCommissionPlan::class, 'plan_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SalesCommissionSettlement::class, 'settlement_id');
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '待结算',
            self::STATUS_IN_BATCH => '已入批次',
            self::STATUS_SETTLED => '已发放',
            self::STATUS_CANCELLED => '已取消',
            default => (string) $status,
        };
    }
}
