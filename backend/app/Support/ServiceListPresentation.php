<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Product;
use App\Models\Service;
use App\Services\ProductCatalog\ProductDisplayNameResolver;

/**
 * 服务列表展示字段的单一口径（D3B-01/D4B-04）：
 * 管理端（AdminServiceListService）与客户端控制台（ServiceTransformService）的
 * product_display_name / product_full_path 共用同一实现，保证同名字段同值同型。
 * 两端只保留字段裁剪差异，不再各自维护取数规则。
 */
final class ServiceListPresentation
{
    /**
     * 产品展示名：订单快照 display_product_name（排除「未配置规格」占位）优先，
     * 其次按商品规格解析器计算。列表路径两端均未预载 order 的
     * display_product_name/config_snapshot 列，因此该分支仅在详情等完整预载场景生效，
     * 两端列表实际取值口径一致。
     */
    public static function productDisplayName(Service $service, ?ProductDisplayNameResolver $resolver = null): string
    {
        $orderDisplayName = trim((string) ($service->order?->display_product_name ?? ''));
        if ($orderDisplayName !== '' && $orderDisplayName !== '未配置规格') {
            return $orderDisplayName;
        }

        if ($service->product instanceof Product) {
            $resolved = ($resolver ?? app(ProductDisplayNameResolver::class))->resolveForProduct(
                $service->product,
                (array) ($service->order?->config_snapshot ?? [])
            );

            return trim((string) ($resolved['product_display_name'] ?? ''));
        }

        return '';
    }

    /**
     * 产品完整路径：一级分组/二级分组/叶子分组名去重后用「/」串联，空段跳过，
     * 全空时回退产品展示名。根分组直接取 product.productGroup.secondProductGroup.firstProductGroup
     * 关系链（与 ServiceResolverService::resolveServiceRootGroup 同一取值，D4B-04 复验同值）。
     */
    public static function serviceProductPath(Service $service, string $productDisplayName): string
    {
        $service->loadMissing([
            'product.productGroup.secondProductGroup.firstProductGroup',
        ]);
        $leafGroup = $service->product?->productGroup;
        $clean = [];
        foreach ([
            trim((string) ($leafGroup?->secondProductGroup?->firstProductGroup?->name ?? '')),
            trim((string) ($leafGroup?->secondProductGroup?->name ?? '')),
            trim((string) ($leafGroup?->name ?? '')),
            trim((string) $productDisplayName),
        ] as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '' || in_array($segment, $clean, true)) {
                continue;
            }
            $clean[] = $segment;
        }

        return $clean !== [] ? implode('/', $clean) : $productDisplayName;
    }
}
