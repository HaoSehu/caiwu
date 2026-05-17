<?php

namespace App\Http\Resources\Product;

use App\Constants\ProductType;
use App\Models\Product;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\ProductProvisionHostname;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $category = $this->resource->relationLoaded('categoryMapping') ? $this->categoryMapping : null;
        $parentCategory = $category && $category->relationLoaded('parent') ? $category->parent : null;
        $supplier = $this->resource->relationLoaded('supplier') ? $this->supplier : null;
        $publicGroupId = $category ? (int) (($category->legacy_group_id ?? 0) ?: ($category->id ?? 0)) : null;
        $productType = (string) $this->product_type;
        $primaryPrice = $this->resolvePrimaryPrice((array) $this->pricing);
        $provisionHostname = ProductProvisionHostname::fromPurchaseRequires((array) ($this->purchase_requires ?? []));
        $displayName = (new ProductDisplayNameResolver)->resolveForProduct($this->resource);

        return [
            'id' => $this->id,
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
            'name' => $this->name,
            'display_name' => (string) ($displayName['product_spec_display'] ?? ''),
            'product_spec_display' => (string) ($displayName['product_spec_display'] ?? ''),
            'cpu_memory_display' => (string) ($displayName['cpu_memory_display'] ?? ''),
            'combined_display_name' => (string) ($displayName['combined_display_name'] ?? ''),
            'product_type' => $productType,
            'type' => $productType,
            'type_label' => ProductType::labelOf($productType),
            'remark' => (string) ($this->remark ?? ''),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'pricing' => (array) $this->pricing,
            'product_prices' => (array) $this->pricing,
            'primary_price' => $primaryPrice,
            'primary_cycle' => $primaryPrice['cycle'] ?? '',
            'setup_fee' => (string) $this->setup_fee,
            'config_options' => $this->config_options ?? [],
            'product_options' => $this->config_options ?? [],
            'purchase_requires' => [
                'require_verification' => (bool) (($this->purchase_requires ?? [])['require_verification'] ?? false),
                'require_phone' => (bool) (($this->purchase_requires ?? [])['require_phone'] ?? false),
                'provision_hostname' => $provisionHostname,
            ],
            'stock' => (int) $this->stock,
            'status' => (int) $this->status,
            'sort_order' => (int) $this->sort_order,
            'provision_module' => $this->provision_module,
            'auto_setup' => (int) $this->auto_setup,
            'provision_hostname' => $provisionHostname,
            'supplier_id' => $this->supplier_id ? (int) $this->supplier_id : null,
            'supplier_name' => $supplier?->name,
            'supplier_product_id' => $this->supplier_product_id ? (int) $this->supplier_product_id : null,
            'orders_count' => (int) ($this->orders_count ?? 0),
            'services_count' => (int) ($this->services_count ?? 0),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
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
