<?php

namespace App\Http\Resources\Product;

use App\Constants\ProductType;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductCategory */
class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ProductCategory $category */
        $category = $this->resource;

        $parent = $category->relationLoaded('parent') ? $category->parent : null;
        $children = $category->relationLoaded('children') ? $category->children : collect();
        $productType = $this->resolveProductTypeValue();
        $legacyGroupId = $category->legacy_group_id !== null ? (int) $category->legacy_group_id : null;
        $parentLegacyGroupId = $parent
            ? (int) (($parent->legacy_group_id ?? 0) ?: $parent->id)
            : null;

        return [
            'id' => (int) ($this->resource->id ?? 0),
            'category_id' => (int) ($this->resource->id ?? 0),
            'group_id' => $legacyGroupId ?? (int) ($this->resource->id ?? 0),
            'legacy_group_id' => $legacyGroupId,
            'parent_id' => $category->parent_id !== null ? (int) $category->parent_id : null,
            'parent_category_id' => $category->parent_id !== null ? (int) $category->parent_id : null,
            'parent_group_id' => $parentLegacyGroupId,
            'product_type' => $productType,
            'product_type_label' => ProductType::labelOf($productType),
            'level' => $category->parent_id ? 2 : 1,
            'name' => $category->name,
            'slogan' => $category->slogan,
            'slug' => $category->slug,
            'sort_order' => (int) $category->sort_order,
            'is_visible' => (int) $category->is_visible,
            'status' => (int) ($category->status ?? 1),
            'parent_name' => $parent?->name,
            'products_count' => (int) ($category->products_count ?? 0),
            'children_count' => (int) ($category->children_count ?? 0),
            'children' => ProductCategoryResource::collection($children),
            'created_at' => $category->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $category->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function resolveProductTypeValue(): string
    {
        return trim((string) ($this->resource->product_type ?? ''));
    }
}
