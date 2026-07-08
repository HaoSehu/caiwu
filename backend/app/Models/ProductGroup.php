<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductGroup extends Model
{
    protected $table = 'product_groups';

    protected $fillable = [
        'parent_id',
        'level',
        'code',
        'product_type',
        'name',
        'slug',
        'description',
        'icon',
        'banner_image',
        'sort_order',
        'is_visible',
        'is_system',
        'legacy_product_type',
        'legacy_product_group_id',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'level' => 'integer',
            'sort_order' => 'integer',
            'is_visible' => 'integer',
            'is_system' => 'integer',
            'legacy_product_group_id' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_group_id');
    }

    public function scopeLevel(Builder $query, int $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * @return array{0:?self,1:?self,2:?self}
     */
    public function hierarchy(): array
    {
        $current = $this;
        $parent = $current->relationLoaded('parent') ? $current->parent : null;
        $grandParent = $parent instanceof self && $parent->relationLoaded('parent') ? $parent->parent : null;

        return match ((int) $current->level) {
            1 => [$current, null, null],
            2 => [$parent instanceof self ? $parent : null, $current, null],
            3 => [
                $grandParent instanceof self ? $grandParent : null,
                $parent instanceof self ? $parent : null,
                $current,
            ],
            default => [null, null, null],
        };
    }

    public function getFirstProductGroupIdAttribute(): ?int
    {
        [$first] = $this->hierarchy();

        return $first instanceof self ? (int) $first->id : null;
    }

    public function getSecondProductGroupIdAttribute(): ?int
    {
        [, $second] = $this->hierarchy();

        return $second instanceof self ? (int) $second->id : null;
    }

    public function getThirdProductGroupIdAttribute(): ?int
    {
        [, , $third] = $this->hierarchy();

        return $third instanceof self ? (int) $third->id : null;
    }
}
