<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property float $reward_rate 新购返利比例（%）0-100
 * @property float $renewal_reward_rate 续费返利比例（%）0-100
 * @property int $status
 * @property string|null $remark
 */
class PromotionAmbassador extends Model
{
    protected $fillable = [
        'name',
        'reward_rate',
        'renewal_reward_rate',
        'status',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'reward_rate' => 'decimal:2',
            'renewal_reward_rate' => 'decimal:2',
            'status' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'promotion_ambassador_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 1);
    }
}
