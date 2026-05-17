<?php

namespace App\Models;

use App\Constants\ProductType as ProductTypeCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $table = 'product_groups';

    protected $fillable = [
        'parent_group_id',
        'parent_id',
        'product_type',
        'name',
        'slug',
        'slogan',
        'is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'parent_group_id' => 'integer',
            'is_visible' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getLegacyGroupIdAttribute(mixed $value): ?int
    {
        if ($value !== null) {
            return (int) $value;
        }

        return isset($this->attributes['id']) ? (int) $this->attributes['id'] : null;
    }

    public function getParentIdAttribute(mixed $value): ?int
    {
        if ($value !== null) {
            return (int) $value;
        }

        $parentGroupId = $this->attributes['parent_group_id'] ?? null;

        return $parentGroupId === null ? null : (int) $parentGroupId;
    }

    public function setParentIdAttribute(mixed $value): void
    {
        $this->attributes['parent_group_id'] = $value === null || $value === '' ? null : (int) $value;
    }

    public function getStatusAttribute(mixed $value): int
    {
        return $value === null ? 1 : (int) $value;
    }

    public function getProductTypeAttribute(mixed $value): string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : ProductTypeCatalog::OTHER;
    }

    public function getTitleAttribute(mixed $value): string
    {
        $normalized = trim((string) ($value ?? ''));

        if ($normalized !== '') {
            return $normalized;
        }

        return trim((string) ($this->attributes['name'] ?? ''));
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_group_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_group_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_group_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', 1);
    }

    public function scopeRoot($query)
    {
        return $query->whereNull('parent_group_id');
    }

    public function getPublicIdAttribute(): int
    {
        return (int) (($this->legacy_group_id ?? 0) ?: $this->id);
    }

    public function getParentPublicIdAttribute(): ?int
    {
        $parentGroupId = $this->getAttribute('parent_group_id');
        if ($parentGroupId === null) {
            return null;
        }

        $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();
        if (! $parent instanceof self) {
            return null;
        }

        return (int) (($parent->legacy_group_id ?? 0) ?: $parent->id);
    }
}
