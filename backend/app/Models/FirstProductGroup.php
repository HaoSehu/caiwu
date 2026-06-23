<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FirstProductGroup extends Model
{
    protected $fillable = [
        'code',
        'name',
        'slug',
        'description',
        'icon',
        'banner_image',
        'sort_order',
        'is_visible',
        'is_system',
        'legacy_product_type',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible' => 'integer',
            'is_system' => 'integer',
        ];
    }

    public function secondProductGroups(): HasMany
    {
        return $this->hasMany(SecondProductGroup::class, 'first_product_group_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'first_product_group_id');
    }
}
