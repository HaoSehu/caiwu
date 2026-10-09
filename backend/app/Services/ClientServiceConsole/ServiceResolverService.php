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

    /**
     * 服务归属的一级菜单 code（first_product_groups.code）。
     *
     * 控制台概览按一级菜单分组，而非业务 product_type：两个一级菜单即使 product_type 同为
     * cloud_server（例如「大陆云服」与「轻量服务器」），也必须落在各自的一级菜单卡片下。
     * 无分组时退回业务类型，保证未归类服务仍有稳定归属。
     */
    public function resolveGroupedOverviewMenuCode(Service $service): string
    {
        $rootGroup = $this->resolveServiceRootGroup($service);
        if ($rootGroup instanceof FirstProductGroup) {
            $code = trim((string) ($rootGroup->code ?? ''));
            if ($code !== '') {
                return $code;
            }
        }

        return $this->resolveGroupedOverviewTypeValue($service);
    }

    public function resolveConsoleMode(Service $service): string
    {
        return $service->product?->console_template === Product::CONSOLE_TEMPLATE_PORT_MAPPING
            ? self::CONSOLE_MODE_NAT
            : self::CONSOLE_MODE_DEFAULT;
    }
}
