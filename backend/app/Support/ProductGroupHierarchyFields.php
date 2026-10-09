<?php

declare(strict_types=1);

namespace App\Support;

use App\Constants\ProductType;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;

class ProductGroupHierarchyFields
{
    /** 一级 = 产品目录，二级 = 地区，三级 = 可用区（官网 / 控制台 / 管理端统一语义）。 */
    public const LEVEL_LABEL_FIRST = '产品目录';

    public const LEVEL_LABEL_SECOND = '地区';

    public const LEVEL_LABEL_THIRD = '可用区';

    public static function levelLabel(?int $level): string
    {
        return match ($level) {
            1 => self::LEVEL_LABEL_FIRST,
            2 => self::LEVEL_LABEL_SECOND,
            3 => self::LEVEL_LABEL_THIRD,
            default => '',
        };
    }

    /**
     * 分组节点稳定 key：`层级:ID`，例如 `2:57`、`3:57`。
     *
     * 二级 #57 与三级 #57 是不同节点，用裸 ID 或裸 slug 会互相覆盖，必须带层级前缀。
     */
    public static function groupNodeKey(?int $level, ?int $id, string $fallback = ''): string
    {
        if ($level !== null && $level > 0 && $id !== null && $id > 0) {
            return $level.':'.$id;
        }

        return $fallback;
    }

    public static function fromProduct(Product $product): array
    {
        [$first, $second, $third] = $product->resolvedProductGroupHierarchy();

        $firstId = $first instanceof FirstProductGroup ? (int) $first->id : null;
        $secondId = $second instanceof SecondProductGroup ? (int) $second->id : null;
        $thirdId = $third instanceof ThirdProductGroup ? (int) $third->id : null;
        $fallbackType = trim((string) ($product->getRawOriginal('product_type') ?: ''));
        $productType = ProductType::businessValueForFirstGroup($first, $fallbackType);
        $firstCode = trim((string) ($first?->code ?? ''));
        $firstName = trim((string) ($first?->name ?? ProductType::labelOf($firstCode)));
        $secondName = trim((string) ($second?->name ?? ''));
        $thirdName = $thirdId !== null ? trim((string) ($third?->name ?? '')) : null;
        $secondDescription = trim((string) ($second?->description ?? ''));
        $thirdDescription = $thirdId !== null ? trim((string) ($third?->description ?? '')) : null;
        $effectiveLevel = $thirdId !== null ? 3 : ($secondId !== null ? 2 : ($firstId !== null ? 1 : null));
        $groupPath = self::pathSegments($firstName, $secondName, $thirdName);

        return [
            'first_product_group_id' => $firstId,
            'first_product_group_code' => $firstCode,
            'first_product_group_name' => $firstName,
            'second_product_group_id' => $secondId,
            'second_product_group_name' => $secondName,
            'second_product_group_description' => $secondDescription,
            'second_product_group_parent_id' => $firstId,
            'second_product_group_parent_name' => $firstName,
            'third_product_group_id' => $thirdId,
            'third_product_group_name' => $thirdName,
            'third_product_group_description' => $thirdDescription,
            'effective_product_group_id' => $thirdId ?? $secondId,
            'effective_product_group_level' => $thirdId !== null ? 3 : ($secondId !== null ? 2 : null),
            'group_level_label' => self::levelLabel($effectiveLevel),
            'group_path' => $groupPath,
            'group_path_text' => implode(' / ', $groupPath),
            'product_type' => $productType,
            'product_type_label' => ProductType::businessLabelOf($productType),
            'service_type_code' => $productType,
        ];
    }

    /**
     * 分组节点（一级 / 二级 / 三级）的层级标注与路径，供资源层与聚合层复用。
     *
     * @return array{group_level_label: string, group_path: list<string>, group_path_text: string}
     */
    public static function forGroupNode(?string $firstName, ?string $secondName, ?string $thirdName, int $level): array
    {
        $segments = self::pathSegments(
            $level >= 1 ? $firstName : null,
            $level >= 2 ? $secondName : null,
            $level >= 3 ? $thirdName : null,
        );

        return [
            'group_level_label' => self::levelLabel($level > 0 ? $level : null),
            'group_path' => $segments,
            'group_path_text' => implode(' / ', $segments),
        ];
    }

    /**
     * 分组路径片段：过滤空段并去重相邻同名段，避免「云服务器 / 云服务器」这类重复。
     *
     * @return list<string>
     */
    private static function pathSegments(?string ...$names): array
    {
        $segments = [];

        foreach ($names as $name) {
            $segment = trim((string) $name);
            if ($segment === '' || end($segments) === $segment) {
                continue;
            }
            $segments[] = $segment;
        }

        return $segments;
    }
}
