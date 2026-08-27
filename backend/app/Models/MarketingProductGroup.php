<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int|null $items_count 营销组商品数（withCount 后可用）
 */
class MarketingProductGroup extends Model
{
    protected $fillable = [
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function items()
    {
        return $this->hasMany(MarketingProductGroupItem::class, 'marketing_product_group_id');
    }

    public function discounts()
    {
        return $this->hasMany(MemberLevelGroupDiscount::class, 'marketing_product_group_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
