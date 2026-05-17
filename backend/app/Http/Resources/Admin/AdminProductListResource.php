<?php

namespace App\Http\Resources\Admin;

use App\Constants\ProductType;
use App\Models\Product;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\ProductProvisionHostname;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class AdminProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $category = $this->resource->relationLoaded('categoryMapping') ? $this->categoryMapping : null;
        $parentCategory = $category && $category->relationLoaded('parent') ? $category->parent : null;
        $publicGroupId = (int) (($category?->legacy_group_id ?? 0) ?: ($category?->id ?? 0));
        $productType = (string) $this->product_type;
        $primaryPrice = $this->resolvePrimaryPrice((array) ($this->pricing ?? []));
        $provisionHostname = ProductProvisionHostname::fromPurchaseRequires((array) ($this->purchase_requires ?? []));
        $displayName = (new ProductDisplayNameResolver)->resolveForProduct($this->resource);

        return [
            'id' => (int) $this->id,
            'category_id' => $this->resource->getAttribute('category_id') ? (int) $this->resource->getAttribute('category_id') : null,
            'category_name' => $category?->name,
            'category_parent_name' => $parentCategory?->name,
            'category_full_name' => $parentCategory ? $parentCategory->name.' / '.($category?->name ?? '') : ($category?->name ?? ''),
            'legacy_group_id' => $publicGroupId,
            'product_group_id' => $publicGroupId,
            'group_id' => $publicGroupId,
            'product_group_name' => $category?->name,
            'group_name' => $category?->name,
            'group_parent_name' => $parentCategory?->name,
            'group_full_name' => $parentCategory ? $parentCategory->name.' / '.($category?->name ?? '') : ($category?->name ?? ''),
            'name' => (string) $this->name,
            'display_name' => (string) ($displayName['product_spec_display'] ?? ''),
            'product_spec_display' => (string) ($displayName['product_spec_display'] ?? ''),
            'cpu_memory_display' => (string) ($displayName['cpu_memory_display'] ?? ''),
            'combined_display_name' => (string) ($displayName['combined_display_name'] ?? ''),
            'product_type' => $productType,
            'type' => $productType,
            'type_label' => ProductType::labelOf($productType),
            'remark' => (string) ($this->remark ?? ''),
            'primary_price' => $primaryPrice,
            'primary_cycle' => $primaryPrice['cycle'] ?? '',
            'stock' => (int) ($this->stock ?? 0),
            'status' => (int) ($this->status ?? 0),
            'sort_order' => (int) ($this->sort_order ?? 0),
            'provision_module' => (string) ($this->provision_module ?? ''),
            'auto_setup' => (int) ($this->auto_setup ?? 0),
            'provision_hostname' => $provisionHostname,
            'provision_hostname_mode' => (string) ($provisionHostname['mode'] ?? ProductProvisionHostname::MODE_SYSTEM),
            'provision_hostname_summary' => ProductProvisionHostname::summary($provisionHostname),
            'supplier_id' => $this->resource->getAttribute('supplier_id') ? (int) $this->resource->getAttribute('supplier_id') : null,
            'supplier_product_id' => $this->resource->getAttribute('supplier_product_id') ? (int) $this->resource->getAttribute('supplier_product_id') : null,
            'orders_count' => (int) ($this->orders_count ?? 0),
            'services_count' => (int) ($this->services_count ?? 0),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function resolvePrimaryPrice(array $pricing): array
    {
        foreach ($pricing as $cycle => $amount) {
            if ((float) $amount > 0) {
                return [
                    'cycle' => (string) $cycle,
                    'amount' => number_format((float) $amount, 2, '.', ''),
                ];
            }
        }

        return [
            'cycle' => '',
            'amount' => '0.00',
        ];
    }
}
