<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * M5 提成方案：计提基数 1=实付金额（含余额支付）2=订单金额；
 * 计提时取「启用中且有效期覆盖今天」的最新一条，费率快照落计提记录。
 *
 * @property int $id
 * @property string $name
 * @property int $basis
 * @property int $is_enabled
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_to
 * @property string|null $remark
 * @property Collection<int, SalesCommissionTier> $tiers
 * @property Collection<int, SalesCommissionProductRate> $productRates
 */
class SalesCommissionPlan extends Model
{
    public const BASIS_PAID_AMOUNT = 1;

    public const BASIS_ORDER_AMOUNT = 2;

    protected $fillable = [
        'name',
        'basis',
        'is_enabled',
        'valid_from',
        'valid_to',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'basis' => 'integer',
            'is_enabled' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(SalesCommissionTier::class, 'plan_id');
    }

    public function productRates(): HasMany
    {
        return $this->hasMany(SalesCommissionProductRate::class, 'plan_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', 1);
    }

    /**
     * 有效期覆盖给定日期（含当天边界，两端均可空）。
     */
    public function coversDate(string|CarbonInterface $date): bool
    {
        $target = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        if ($this->valid_from !== null && $target < $this->valid_from->toDateString()) {
            return false;
        }

        if ($this->valid_to !== null && $target > $this->valid_to->toDateString()) {
            return false;
        }

        return true;
    }
}
