<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThirdProductGroup extends Model
{
    protected $fillable = [
        'second_product_group_id',
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
            'second_product_group_id' => 'integer',
            'sort_order' => 'integer',
            'is_visible' => 'integer',
            'legacy_product_group_id' => 'integer',
        ];
    }

    public function secondProductGroup(): BelongsTo
    {
        return $this->belongsTo(SecondProductGroup::class, 'second_product_group_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'third_product_group_id');
    }
}
