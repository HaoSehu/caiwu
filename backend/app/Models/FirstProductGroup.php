<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\DatabaseSchema;

class FirstProductGroup extends ProductGroup
{
    protected $table = 'first_product_groups';

    protected static function booted(): void
    {
        static::addGlobalScope('first_product_group_level', function (Builder $builder): void {
            if (self::hasLevelColumn()) {
                $builder->where($builder->getModel()->qualifyColumn('level'), 1);
            }
        });
        static::creating(function (self $group): void {
            if (self::hasLevelColumn()) {
                $group->level = 1;
            }
        });
    }

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
        'product_type',
    ];

    public function secondProductGroups(): HasMany
    {
        return $this->hasMany(SecondProductGroup::class, 'first_product_group_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_group_id');
    }

    private static function hasLevelColumn(): bool
    {
        try {
            return DatabaseSchema::hasColumn((new self)->getTable(), 'level');
        } catch (\Throwable) {
            return false;
        }
    }
}
