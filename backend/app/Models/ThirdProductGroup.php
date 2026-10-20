<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property SecondProductGroup|null $secondProductGroup 上级二级分组
 * @property string $name 分组名
 * @property string|null $description 分组描述
 */
class ThirdProductGroup extends Model
{
    protected $table = 'third_product_groups';

    protected $fillable = [
        'second_product_group_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'second_product_group_id' => 'integer',
            'sort_order' => 'integer',
            'is_visible' => 'integer',
        ];
    }

    public function secondProductGroup(): BelongsTo
    {
        return $this->belongsTo(SecondProductGroup::class, 'second_product_group_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_group_id');
    }
}
