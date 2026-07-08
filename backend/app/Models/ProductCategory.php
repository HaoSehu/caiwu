<?php

/**
 * @deprecated 商品分组真源已恢复为 product_groups 自引用树。
 *             此 Model 仅保留供历史命令引用，新代码请使用 ProductGroup。
 */

namespace App\Models;

use App\Constants\ProductType as ProductTypeCatalog;
use App\Support\DatabaseSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $table = 'product_groups';

    protected $fillable = [
        'parent_group_id',
        'parent_id',
        'level',
        'code',
        'product_type',
        'name',
        'slug',
        'description',
        'slogan',
        'is_visible',
        'is_system',
        'sort_order',
        'legacy_product_type',
        'legacy_product_group_id',
    ];

    protected function casts(): array
    {
        return [
            'parent_group_id' => 'integer',
            'parent_id' => 'integer',
            'level' => 'integer',
            'is_visible' => 'integer',
            'is_system' => 'integer',
            'sort_order' => 'integer',
            'legacy_product_group_id' => 'integer',
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

        $parentGroupId = $this->attributes['parent_id'] ?? $this->attributes['parent_group_id'] ?? null;

        return $parentGroupId === null ? null : (int) $parentGroupId;
    }

    public function setParentIdAttribute(mixed $value): void
    {
        $column = $this->parentColumnName();

        $this->attributes[$column] = $value === null || $value === '' ? null : (int) $value;
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
        return $this->belongsTo(self::class, $this->parentColumnName());
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, $this->parentColumnName());
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
        return $query->whereNull($this->parentColumnName());
    }

    public function getPublicIdAttribute(): int
    {
        return (int) (($this->legacy_group_id ?? 0) ?: $this->id);
    }

    public function getParentPublicIdAttribute(): ?int
    {
        $parentGroupId = $this->getAttribute($this->parentColumnName());
        if ($parentGroupId === null) {
            return null;
        }

        $parent = $this->relationLoaded('parent') ? $this->parent : $this->parent()->first();
        if (! $parent instanceof self) {
            return null;
        }

        return (int) (($parent->legacy_group_id ?? 0) ?: $parent->id);
    }

    private function parentColumnName(): string
    {
        try {
            return DatabaseSchema::hasColumn($this->getTable(), 'parent_id') ? 'parent_id' : 'parent_group_id';
        } catch (\Throwable) {
            return 'parent_id';
        }
    }
}
