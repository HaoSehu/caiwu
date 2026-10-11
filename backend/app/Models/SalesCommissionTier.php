<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M5 提成方案阶梯：min_amount ≤ 计提基数 < max_amount（max_amount NULL=∞）。
 *
 * @property int $id
 * @property int $plan_id
 * @property float $min_amount
 * @property float|null $max_amount
 * @property float $rate
 */
class SalesCommissionTier extends Model
{
    protected $fillable = [
        'plan_id',
        'min_amount',
        'max_amount',
        'rate',
    ];

    protected function casts(): array
    {
        return [
            'plan_id' => 'integer',
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'rate' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SalesCommissionPlan::class, 'plan_id');
    }

    /**
     * 阶梯边界判定：min ≤ base < max（max 为空视为 ∞）。
     */
    public function coversAmount(float $baseAmount): bool
    {
        if ($baseAmount < (float) $this->min_amount) {
            return false;
        }

        if ($this->max_amount !== null && $baseAmount >= (float) $this->max_amount) {
            return false;
        }

        return true;
    }
}
