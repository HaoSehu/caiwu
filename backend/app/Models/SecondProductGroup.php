<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\DatabaseSchema;

class SecondProductGroup extends ProductGroup
{
    protected $table = 'second_product_groups';

    protected static function booted(): void
    {
        static::addGlobalScope('second_product_group_level', function (Builder $builder): void {
            if (self::hasLevelColumn()) {
                $builder->where($builder->getModel()->qualifyColumn('level'), 2);
            }
        });
        static::creating(function (self $group): void {
            if (self::hasLevelColumn()) {
                $group->level = 2;
            }
        });
    }

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

    public function firstProductGroup(): BelongsTo
    {
        return $this->belongsTo(FirstProductGroup::class, 'first_product_group_id');
    }

    public function getFirstProductGroupIdAttribute(mixed $value = null): ?int
    {
        $raw = $value ?? ($this->attributes['first_product_group_id'] ?? $this->attributes['parent_id'] ?? null);

        return (int) ($raw ?? 0) > 0 ? (int) $raw : null;
    }

    public function thirdProductGroups(): HasMany
    {
        return $this->hasMany(ThirdProductGroup::class, 'second_product_group_id');
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
