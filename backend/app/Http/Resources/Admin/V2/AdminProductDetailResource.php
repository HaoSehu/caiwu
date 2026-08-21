<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\V2;

use App\Constants\ProductType;
use App\Models\Product;
use App\Models\ProductUpstreamBinding;
use App\Models\Supplier;
use App\Models\SupplierPluginBinding;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Support\ProductGroupHierarchyFields;
use App\Support\ProductProvisionHostname;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class AdminProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $display = app(ProductDisplayNameResolver::class)->resolveForProduct($product);
        $hierarchy = ProductGroupHierarchyFields::fromProduct($product);
        $productType = (string) ($hierarchy['product_type'] ?? $hierarchy['service_type_code'] ?? $product->product_type ?? '');
        $purchaseRequires = $this->removeSensitiveKeys((array) ($product->purchase_requires ?? []));

        return [
            'id' => (int) $product->id,
            'display' => [
                'display_name' => (string) $display['product_display_name'],
                'product_spec_display' => (string) $display['product_spec_display'],
                'custom_display_name' => (string) $display['custom_display_name'],
                'cpu_memory_display' => (string) $display['cpu_memory_display'],
                'combined_display_name' => (string) $display['combined_display_name'],
                'remark' => (string) ($product->remark ?? ''),
            ],
            'classification' => [
                'product_type' => $productType,
                'product_type_label' => ProductType::businessLabelOf($productType),
                ...$hierarchy,
                'category_full_name' => $this->categoryFullName($hierarchy),
            ],
            'pricing' => [
                'items' => $this->pricing((array) ($product->pricing ?? [])),
                'primary_price' => $this->primaryPrice((array) ($product->pricing ?? [])),
                'setup_fee' => number_format((float) ($product->setup_fee ?? 0), 2, '.', ''),
            ],
            'configuration' => [
                'console_template' => $product->console_template,
                'config_options' => $this->trimConfigOptions($product->config_options),
            ],
            'purchase_requirements' => [
                'require_verification' => (bool) ($purchaseRequires['require_verification'] ?? false),
                'require_phone' => (bool) ($purchaseRequires['require_phone'] ?? false),
                'provision_hostname' => ProductProvisionHostname::fromPurchaseRequires($purchaseRequires),
            ],
            'provisioning' => [
                'stock' => (int) ($product->stock ?? -1),
                'auto_setup' => (int) ($product->auto_setup ?? 0),
            ],
            'upstream_binding' => $this->upstreamBinding($product),
            'statistics' => [
                'orders_count' => (int) ($product->orders_count ?? 0),
                'services_count' => (int) ($product->services_count ?? $product->total_services_count ?? 0),
                'active_services_count' => (int) ($product->active_services_count ?? 0),
            ],
            'lifecycle' => [
                'status' => (int) ($product->status ?? 0),
                'lifecycle_status' => $product->trashed() ? 'deleted' : 'active',
                'deleted_at' => $product->deleted_at?->format('Y-m-d H:i:s'),
                'sort_order' => (int) ($product->sort_order ?? 0),
            ],
            'timestamps' => [
                'created_at' => $product->created_at?->format('Y-m-d H:i:s'),
                'updated_at' => $product->updated_at?->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function upstreamBinding(Product $product): ?array
    {
        $bindings = $product->relationLoaded('upstreamBindings')
            ? $product->getRelation('upstreamBindings')
            : collect();

        if (! $bindings instanceof Collection || $bindings->isEmpty()) {
            return null;
        }

        $binding = $bindings
            ->sortByDesc(fn (ProductUpstreamBinding $item): array => [
                (int) ($item->status ?? 0),
                (int) $item->id,
            ])
            ->first();

        if (! $binding instanceof ProductUpstreamBinding) {
            return null;
        }

        $supplierBinding = $binding->relationLoaded('supplierPluginBinding') ? $binding->supplierPluginBinding : null;
        $supplier = $supplierBinding instanceof SupplierPluginBinding && $supplierBinding->relationLoaded('supplier')
            ? $supplierBinding->supplier
            : null;

        return [
            'id' => (int) $binding->id,
            'provider_key' => (string) ($binding->provider_key ?? ''),
            'supplier_id' => $supplierBinding instanceof SupplierPluginBinding ? (int) $supplierBinding->supplier_id : null,
            'supplier_name' => $supplier instanceof Supplier ? (string) $supplier->name : null,
            'upstream_product_id' => (string) ($binding->upstream_product_id ?? ''),
            'status' => (int) ($binding->status ?? 0),
            'auto_setup' => (bool) ($binding->auto_setup ?? false),
            'last_synced_at' => $binding->last_synced_at?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array<string, string>
     */
    private function pricing(array $pricing): array
    {
        return collect($pricing)
            ->mapWithKeys(fn (mixed $amount, mixed $cycle): array => [
                (string) $cycle => number_format((float) $amount, 2, '.', ''),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array{cycle: string, amount: string}|null
     */
    private function primaryPrice(array $pricing): ?array
    {
        foreach (['monthly', 'quarterly', 'semiannually', 'annually'] as $cycle) {
            if (! array_key_exists($cycle, $pricing)) {
                continue;
            }

            return [
                'cycle' => $cycle,
                'amount' => number_format((float) $pricing[$cycle], 2, '.', ''),
            ];
        }

        foreach ($pricing as $cycle => $amount) {
            return [
                'cycle' => (string) $cycle,
                'amount' => number_format((float) $amount, 2, '.', ''),
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $hierarchy
     */
    private function categoryFullName(array $hierarchy): string
    {
        return collect([
            $hierarchy['first_product_group_name'] ?? '',
            $hierarchy['second_product_group_name'] ?? '',
            $hierarchy['third_product_group_name'] ?? '',
        ])
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->implode(' / ');
    }

    private function removeSensitiveKeys(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                continue;
            }

            $clean[$key] = $this->removeSensitiveKeys($item);
        }

        return $clean;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (['password', 'secret', 'api_key', 'raw_response', 'third_party_response'] as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function trimConfigOptions(mixed $configOptions): array
    {
        if (! is_array($configOptions)) {
            return [];
        }

        return collect($configOptions)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item, int $index): array {
                $clean = $this->removeSensitiveKeys($item);

                return [
                    'id' => isset($clean['id']) ? (int) $clean['id'] : 0,
                    'field' => trim((string) ($clean['field'] ?? '')),
                    'spec_key' => trim((string) ($clean['spec_key'] ?? '')),
                    'name' => trim((string) ($clean['name'] ?? $clean['option_name'] ?? '')),
                    'description' => trim((string) ($clean['description'] ?? '')),
                    'hidden' => (int) ($clean['hidden'] ?? 0),
                    'required' => (int) ($clean['required'] ?? 0),
                    'sort_order' => (int) ($clean['sort_order'] ?? $clean['order'] ?? ($index + 1)),
                    'option_type' => isset($clean['option_type']) ? (int) $clean['option_type'] : null,
                    'option_mode' => trim((string) ($clean['option_mode'] ?? '')),
                    'parameter' => trim((string) ($clean['parameter'] ?? '')),
                    'qty_minimum' => $clean['qty_minimum'] ?? null,
                    'qty_maximum' => $clean['qty_maximum'] ?? null,
                    'qty_step' => $clean['qty_step'] ?? null,
                    'qty_stage' => $clean['qty_stage'] ?? null,
                    'suffix_text' => trim((string) ($clean['suffix_text'] ?? '')),
                    'sub' => $this->trimSubOptions($clean['sub'] ?? []),
                ];
            })
            ->values()
            ->all();
    }

    private function trimSubOptions(mixed $subOptions): array
    {
        if (! is_array($subOptions)) {
            return [];
        }

        return collect($subOptions)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item, int $index): array {
                $clean = $this->removeSensitiveKeys($item);

                return [
                    'id' => isset($clean['id']) ? (string) $clean['id'] : '',
                    'label' => trim((string) ($clean['label'] ?? '')),
                    'version' => trim((string) ($clean['version'] ?? '')),
                    'value' => trim((string) ($clean['value'] ?? '')),
                    'fee' => is_numeric($clean['fee'] ?? null) ? number_format((float) $clean['fee'], 2, '.', '') : '0.00',
                    'setup_fee' => is_numeric($clean['setup_fee'] ?? null) ? number_format((float) $clean['setup_fee'], 2, '.', '') : '0.00',
                    'order' => (int) ($clean['order'] ?? ($index + 1)),
                    'icon' => trim((string) ($clean['icon'] ?? '')),
                    'group_id' => (string) ($clean['group_id'] ?? ''),
                    'parameter' => trim((string) ($clean['parameter'] ?? '')),
                    'pricings' => $this->trimPricings($clean['pricings'] ?? []),
                ];
            })
            ->values()
            ->all();
    }

    private function trimPricings(mixed $pricings): array
    {
        if (! is_array($pricings)) {
            return [];
        }

        $clean = [];
        foreach ($pricings as $cycle => $amount) {
            $cycle = (string) $cycle;
            $clean[$cycle] = is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : '0.00';
        }

        return $clean;
    }
}
