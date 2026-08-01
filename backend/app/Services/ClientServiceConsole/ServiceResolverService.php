<?php

declare(strict_types=1);

namespace App\Services\ClientServiceConsole;

use App\Constants\ProductType;
use App\Models\FirstProductGroup;
use App\Models\Product;
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

    public function resolveServiceRootGroup(Service $service): ?FirstProductGroup
    {
        $service->loadMissing('product.productGroup.secondProductGroup.firstProductGroup');

        return $service->product?->productGroup?->secondProductGroup?->firstProductGroup;
    }

    public function resolveGroupedOverviewTypeValue(Service $service): string
    {
        $service->loadMissing('product.productGroup.secondProductGroup.firstProductGroup');

        $productType = trim((string) ($service->product?->product_type ?? ''));
        if ($productType !== '') {
            return ProductType::normalizeBusinessValue($productType);
        }

        $serviceTypeCode = trim((string) ($service->product?->service_type_code ?? ''));
        if ($serviceTypeCode !== '') {
            return ProductType::normalizeBusinessValue($serviceTypeCode);
        }

        $firstGroup = $service->product?->productGroup?->secondProductGroup?->firstProductGroup;
        if ($firstGroup instanceof FirstProductGroup) {
            return ProductType::businessValueForFirstGroup($firstGroup);
        }

        return ProductType::OTHER;
    }

    public function resolveConsoleMode(Service $service): string
    {
        return $service->product?->console_template === Product::CONSOLE_TEMPLATE_PORT_MAPPING
            ? self::CONSOLE_MODE_NAT
            : self::CONSOLE_MODE_DEFAULT;
    }
}
