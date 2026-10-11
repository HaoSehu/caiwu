<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * M5 按产品差异化费率：rate（比例%）与 fixed_amount（固定金额）二选一，fixed_amount 优先。
 * 产品费率命中优先于方案阶梯。
 *
 * @property int $id
 * @property int $plan_id
 * @property int $product_id
 * @property float|null $rate
 * @property float|null $fixed_amount
 * @property Product|null $product
 */
class SalesCommissionProductRate extends Model
{
    protected $fillable = [
        'plan_id',
        'product_id',
        'rate',
        'fixed_amount',
    ];

    protected function casts(): array
    {
        return [
            'plan_id' => 'integer',
            'product_id' => 'integer',
            'rate' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SalesCommissionPlan::class, 'plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
