<?php

declare(strict_types=1);

namespace App\Services\Upstream;

use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;

final class ProviderResolver
{
    public function __construct(
        private readonly ProviderRegistry $registry,
    ) {}

    public function normalizeKey(string $raw): ?string
    {
        $normalized = trim($raw);
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            ProviderKey::MOFANG_FINANCE_API => ProviderKey::HOSTING_PANEL_API,
            default => $normalized,
        };
    }

    public function resolveForSupplier(Supplier $supplier): ResolvedProvider
    {
        return $this->resolveByRawKey((string) ($supplier->interface_type ?? ''));
    }

    public function resolveForProduct(Product $product): ResolvedProvider
    {
        $product->loadMissing('supplier');

        return $this->resolveByRawKey(
            (string) ($product->provision_module ?: ($product->supplier?->interface_type ?? ''))
        );
    }

    public function resolveForService(Service $service): ResolvedProvider
    {
        $service->loadMissing('product.supplier');

        $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
        $rawKey = (string) (
            $provisionData['provider']
            ?? ($service->product?->provision_module ?: ($service->product?->supplier?->interface_type ?? ''))
        );

        return $this->resolveByRawKey($rawKey);
    }

    public function resolveByRawKey(?string $rawKey): ResolvedProvider
    {
        $rawKeyString = trim((string) $rawKey);
        $resolvedKey = $this->normalizeKey($rawKeyString);

        return new ResolvedProvider(
            $rawKeyString !== '' ? $rawKeyString : null,
            $resolvedKey,
            $this->registry->find($resolvedKey),
        );
    }
}
