<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\DatabaseSchema;

class ThirdProductGroup extends ProductGroup
{
    protected $table = 'third_product_groups';

    protected static function booted(): void
    {
        static::addGlobalScope('third_product_group_level', function (Builder $builder): void {
            if (self::hasLevelColumn()) {
                $builder->where($builder->getModel()->qualifyColumn('level'), 3);
            }
        });
        static::creating(function (self $group): void {
            if (self::hasLevelColumn()) {
                $group->level = 3;
            }
        });
    }

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

    public function secondProductGroup(): BelongsTo
    {
        return $this->belongsTo(SecondProductGroup::class, 'second_product_group_id');
    }

    public function getSecondProductGroupIdAttribute(mixed $value = null): ?int
    {
        $raw = $value ?? ($this->attributes['second_product_group_id'] ?? $this->attributes['parent_id'] ?? null);

        return (int) ($raw ?? 0) > 0 ? (int) $raw : null;
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
