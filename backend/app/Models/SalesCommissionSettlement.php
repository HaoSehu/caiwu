<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * M5 结算批次：线下发放的管理登记制（纯登记不动钱）。
 *
 * @property int $id
 * @property int $admin_user_id
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property float $total_amount
 * @property int $commission_count
 * @property int $status
 * @property int|null $operator_id
 * @property Carbon|null $settled_at
 * @property string|null $remark
 * @property AdminUser|null $adminUser
 * @property AdminUser|null $operator
 * @property Collection<int, SalesCommission> $commissions
 */
class SalesCommissionSettlement extends Model
{
    public const STATUS_PENDING = 0;

    public const STATUS_PAID = 1;

    public const STATUS_REJECTED = 2;

    protected $fillable = [
        'admin_user_id',
        'period_start',
        'period_end',
        'total_amount',
        'commission_count',
        'status',
        'operator_id',
        'settled_at',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'admin_user_id' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'total_amount' => 'decimal:2',
            'commission_count' => 'integer',
            'status' => 'integer',
            'operator_id' => 'integer',
            'settled_at' => 'datetime',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'operator_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(SalesCommission::class, 'settlement_id');
    }

    public static function statusLabel(int $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => '待审核',
            self::STATUS_PAID => '已发放',
            self::STATUS_REJECTED => '已驳回',
            default => (string) $status,
        };
    }
}
