<?php

declare(strict_types=1);

namespace App\Services\ClientServiceConsole;

use App\Constants\ProductType;
use App\Models\ProductCategory;
use App\Models\Service;

/**
 * 服务属性解析共享服务（无外部依赖）
 * 负责：resolveServiceRootGroup、resolveGroupedOverviewTypeValue、resolveConsoleMode
 * 被 ServiceOverviewService 和 ServiceTransformService 共同依赖，以打破循环依赖。
 */
class ServiceResolverService
{
    private const CONSOLE_MODE_DEFAULT = 'default';

    private const CONSOLE_MODE_NAT = 'nat';

    private const NAT_CONSOLE_TYPE_VALUES = [
        'nat',
        'cloud_desktop',
        'cloud_pc',
        'cloudpc',
        'nat_console',
    ];

    private const NAT_CONSOLE_TEXT_KEYWORDS = [
        '云电脑',
        '云桌面',
        '桌面云',
        'cloud pc',
        'cloud desktop',
    ];

    public function resolveServiceRootGroup(Service $service): ?ProductCategory
    {
        $service->loadMissing('product.categoryMapping.parent');
        $group = $service->product?->categoryMapping;

        return $group?->parent ?: $group;
    }

    public function resolveGroupedOverviewTypeValue(Service $service): string
    {
        $service->loadMissing('product.categoryMapping.parent');

        $groupType = trim((string) ($service->product?->categoryMapping?->product_type ?? ''));
        if ($groupType !== '') {
            return $groupType;
        }

        $rootGroupType = trim((string) ($this->resolveServiceRootGroup($service)?->product_type ?? ''));
        if ($rootGroupType !== '') {
            return $rootGroupType;
        }

        $productType = trim((string) ($service->product?->product_type ?? ''));
        if ($productType !== '') {
            return $productType;
        }

        return ProductType::OTHER;
    }

    public function resolveConsoleMode(Service $service, array $provisionData = []): string
    {
        $provisionData = $provisionData !== [] ? $provisionData : (array) ($service->provision_data ?? []);
        $explicitMode = strtolower(trim((string) ($provisionData['console_mode'] ?? '')));

        if (in_array($explicitMode, [self::CONSOLE_MODE_DEFAULT, self::CONSOLE_MODE_NAT], true)) {
            return $explicitMode;
        }

        return $this->isNatConsoleService($service, $provisionData)
            ? self::CONSOLE_MODE_NAT
            : self::CONSOLE_MODE_DEFAULT;
    }

    private function isNatConsoleService(Service $service, array $provisionData = []): bool
    {
        $provisionData = $provisionData !== [] ? $provisionData : (array) ($service->provision_data ?? []);
        $catalogProductType = $this->normalizeConsoleTypeValue($this->resolveGroupedOverviewTypeValue($service));

        if (in_array($catalogProductType, self::NAT_CONSOLE_TYPE_VALUES, true)) {
            return true;
        }

        foreach ([
            (string) ($service->product?->name ?? ''),
            (string) ($service->product?->product_type ?? ''),
            (string) ($service->product?->categoryMapping?->name ?? ''),
            (string) ($service->product?->categoryMapping?->title ?? ''),
            (string) ($service->product?->categoryMapping?->product_type ?? ''),
            (string) ($this->resolveServiceRootGroup($service)?->name ?? ''),
            (string) ($this->resolveServiceRootGroup($service)?->title ?? ''),
            (string) ($this->resolveServiceRootGroup($service)?->product_type ?? ''),
            (string) ($provisionData['upstream_product_name'] ?? ''),
        ] as $candidateText) {
            if ($this->containsNatConsoleKeyword($candidateText)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeConsoleTypeValue(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace(['-', ' '], '_', $normalized);

        return preg_replace('/_+/u', '_', $normalized) ?? $normalized;
    }

    private function containsNatConsoleKeyword(string $value): bool
    {
        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return false;
        }

        foreach (self::NAT_CONSOLE_TEXT_KEYWORDS as $keyword) {
            if (mb_stripos($normalized, mb_strtolower($keyword)) !== false) {
                return true;
            }
        }

        return false;
    }
}
