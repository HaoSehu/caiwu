<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecondProductGroup extends Model
{
    protected $fillable = [
        'first_product_group_id',
        'name',
        'slug',
        'description',
        'banner_image',
        'sort_order',
        'is_visible',
        'legacy_product_group_id',
    ];

    protected function casts(): array
    {
        return [
            'first_product_group_id' => 'integer',
            'sort_order' => 'integer',
            'is_visible' => 'integer',
            'legacy_product_group_id' => 'integer',
        ];
    }

    public function firstProductGroup(): BelongsTo
    {
        return $this->belongsTo(FirstProductGroup::class, 'first_product_group_id');
    }

    public function thirdProductGroups(): HasMany
    {
        return $this->hasMany(ThirdProductGroup::class, 'second_product_group_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'second_product_group_id');
    }
}
