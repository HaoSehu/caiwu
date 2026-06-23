<?php

namespace App\Http\Resources\Admin;

use App\Constants\ProductType;
use App\Models\Product;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\ProductGroupHierarchyFields;
use App\Support\ProductProvisionHostname;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class AdminProductListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $productType = (string) $this->product_type;
        $hierarchyFields = ProductGroupHierarchyFields::fromProduct($this->resource);
        $pricing = (array) ($this->pricing ?? []);
        $primaryPrice = $this->resolvePrimaryPrice($pricing);
        $provisionHostname = ProductProvisionHostname::fromPurchaseRequires((array) ($this->purchase_requires ?? []));
        $displayName = (new ProductDisplayNameResolver)->resolveForProduct($this->resource);
        $adminSpecDisplay = $this->resolveAdminProductSpecDisplayName($displayName);
        $adminCustomDisplayName = $this->resolveAdminCustomDisplayName($displayName, $adminSpecDisplay);
        $adminDisplayName = $this->resolveAdminProductDisplayName($displayName, $adminSpecDisplay, $adminCustomDisplayName);

        return [
            'id' => (int) $this->id,
            'effective_product_group_name' => $hierarchyFields['third_product_group_name'] ?? $hierarchyFields['second_product_group_name'] ?? '',
            'effective_product_group_parent_name' => $hierarchyFields['second_product_group_name'] ?? '',
            'effective_product_group_full_name' => $this->resolveHierarchyFullName($hierarchyFields),
            'name' => $adminDisplayName,
            'display_name' => $adminDisplayName,
            'custom_display_name' => $adminCustomDisplayName,
            'product_spec_display' => $adminSpecDisplay,
            'product_display_name' => $adminDisplayName,
            'cpu_memory_display' => (string) ($displayName['cpu_memory_display'] ?? ''),
            'combined_display_name' => (string) ($displayName['combined_display_name'] ?? ''),
            'product_type' => $productType,
            'type' => $productType,
            'type_label' => ProductType::labelOf($productType),
            ...$hierarchyFields,
            'remark' => (string) ($this->remark ?? ''),
            'primary_price' => $primaryPrice,
            'monthly_price' => $this->resolveMonthlyPrice($pricing),
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
            'total_services_count' => (int) ($this->total_services_count ?? 0),
            'services_count' => (int) ($this->services_count ?? 0),
            'active_services_count' => (int) ($this->services_count ?? 0),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function resolveHierarchyFullName(array $hierarchyFields): string
    {
        return collect([
            $hierarchyFields['first_product_group_name'] ?? '',
            $hierarchyFields['second_product_group_name'] ?? '',
            $hierarchyFields['third_product_group_name'] ?? '',
        ])
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->implode(' / ');
    }

    private function resolveAdminProductDisplayName(array $displayName, string $specDisplay, string $customDisplayName): string
    {
        foreach ([
            $customDisplayName,
            $specDisplay,
            $displayName['product_display_name'] ?? '',
        ] as $candidate) {
            $normalized = trim((string) $candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '未配置规格 #'.(int) $this->id;
    }

    private function resolveAdminProductSpecDisplayName(array $displayName): string
    {
        foreach ([
            $displayName['cpu_memory_slug_display'] ?? '',
            $displayName['cpu_memory_display'] ?? '',
            $displayName['product_spec_display'] ?? '',
            $displayName['product_display_name'] ?? '',
        ] as $candidate) {
            $normalized = trim((string) $candidate);
            if ($this->isMeaningfulProductDisplaySegment($normalized)) {
                return $normalized;
            }
        }

        return '未配置规格 #'.(int) $this->id;
    }

    private function resolveAdminCustomDisplayName(array $displayName, string $specDisplay): string
    {
        $customDisplayName = trim((string) ($displayName['custom_display_name'] ?? ''));
        if ($customDisplayName === '') {
            return '';
        }

        $defaultCandidates = [
            $specDisplay,
            $displayName['cpu_memory_slug_display'] ?? '',
            $displayName['cpu_memory_display'] ?? '',
            $displayName['product_spec_display'] ?? '',
        ];

        $customComparable = $this->normalizeDisplayComparable($customDisplayName);
        foreach ($defaultCandidates as $candidate) {
            if ($customComparable !== '' && $customComparable === $this->normalizeDisplayComparable((string) $candidate)) {
                return '';
            }
        }

        return $customDisplayName;
    }

    private function normalizeDisplayComparable(string $value): string
    {
        $normalized = strtolower((string) preg_replace('/[\s_-]+/u', '', trim($value)));
        $normalized = (string) preg_replace('/(\d+(?:\.\d+)?)gb/i', '$1gib', $normalized);
        $normalized = (string) preg_replace('/(\d+(?:\.\d+)?)g(?![a-z])/i', '$1gib', $normalized);

        return $normalized;
    }

    private function isMeaningfulProductDisplaySegment(string $value): bool
    {
        return $value !== '' && ! str_starts_with($value, '未配置规格 #');
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

    private function resolveMonthlyPrice(array $pricing): string
    {
        $amount = $pricing['monthly'] ?? null;

        if (! is_numeric($amount)) {
            return '0.00';
        }

        return number_format(max(0, (float) $amount), 2, '.', '');
    }
}
