<?php

declare(strict_types=1);

namespace App\Support;

use App\Constants\ProductType;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use Illuminate\Database\Eloquent\Builder;

/**
 * 站点可见商品分组唯一查询入口。
 *
 * 前台（官网 / 用户控制台）所有「站点可见分组」查询必须经此类，可见性口径只有这一处真源：
 *  - 可见性 = 一级 / 二级 / 三级分组 is_visible = 1，且一级菜单 code 在 ProductType::visibleValues() 内；
 *  - 传入一级菜单 code 时按 first_product_groups.code 精确命中，绝不用 product_type 兜底并项，
 *    避免「大陆云服」与「轻量服务器」同为 cloud_server 时被合并成同一组；
 *  - product_type 仅在未传一级菜单 code 时作为独立筛选条件（AND），不与 code 做 OR 并项。
 */
class SiteProductGroupQuery
{
    /**
     * 站点可见的二级分组查询。
     *
     * @param  string|null  $firstGroupCode  一级菜单 code（精确命中 first_product_groups.code）
     * @param  string|null  $productType  业务类型值（仅在未传 code 时生效）
     */
    public static function visibleSecondGroups(?string $firstGroupCode = null, ?string $productType = null): Builder
    {
        $visibleCodes = ProductType::visibleValues();
        if ($visibleCodes === []) {
            return SecondProductGroup::query()->whereRaw('1 = 0');
        }

        return SecondProductGroup::query()
            ->select('second_product_groups.*')
            ->join('first_product_groups', 'first_product_groups.id', '=', 'second_product_groups.first_product_group_id')
            ->where('second_product_groups.is_visible', 1)
            ->where('first_product_groups.is_visible', 1)
            ->whereIn('first_product_groups.code', $visibleCodes)
            ->when(self::normalizeCode($firstGroupCode) !== '', function (Builder $query) use ($firstGroupCode): void {
                $query->where('first_product_groups.code', self::normalizeCode($firstGroupCode));
            }, function (Builder $query) use ($productType): void {
                $businessType = trim((string) $productType);
                if ($businessType !== '') {
                    $query->where('first_product_groups.product_type', ProductType::normalizeBusinessValue($businessType));
                }
            });
    }

    /**
     * 站点可见的三级分组查询。
     */
    public static function visibleThirdGroups(?string $firstGroupCode = null, ?string $productType = null): Builder
    {
        $visibleCodes = ProductType::visibleValues();
        if ($visibleCodes === []) {
            return ThirdProductGroup::query()->whereRaw('1 = 0');
        }

        return ThirdProductGroup::query()
            ->select('third_product_groups.*')
            ->join('second_product_groups', 'second_product_groups.id', '=', 'third_product_groups.second_product_group_id')
            ->join('first_product_groups', 'first_product_groups.id', '=', 'second_product_groups.first_product_group_id')
            ->where('third_product_groups.is_visible', 1)
            ->where('second_product_groups.is_visible', 1)
            ->where('first_product_groups.is_visible', 1)
            ->whereIn('first_product_groups.code', $visibleCodes)
            ->when(self::normalizeCode($firstGroupCode) !== '', function (Builder $query) use ($firstGroupCode): void {
                $query->where('first_product_groups.code', self::normalizeCode($firstGroupCode));
            }, function (Builder $query) use ($productType): void {
                $businessType = trim((string) $productType);
                if ($businessType !== '') {
                    $query->where('first_product_groups.product_type', ProductType::normalizeBusinessValue($businessType));
                }
            });
    }

    public static function resolveVisibleSecondGroup(int $groupId): ?SecondProductGroup
    {
        if ($groupId <= 0) {
            return null;
        }

        $group = self::visibleSecondGroups()
            ->with(['firstProductGroup'])
            ->where('second_product_groups.id', $groupId)
            ->first();

        return $group instanceof SecondProductGroup ? $group : null;
    }

    public static function resolveVisibleThirdGroup(int $groupId): ?ThirdProductGroup
    {
        if ($groupId <= 0) {
            return null;
        }

        $group = self::visibleThirdGroups()
            ->with(['secondProductGroup.firstProductGroup'])
            ->where('third_product_groups.id', $groupId)
            ->first();

        return $group instanceof ThirdProductGroup ? $group : null;
    }

    /**
     * 批量判定分组是否站点可见，返回可见的二级 / 三级分组 ID。
     *
     * @param  array<int, int>  $groupIds
     * @return array{second: list<int>, third: list<int>}
     */
    public static function resolveVisibleGroupIds(array $groupIds): array
    {
        $normalized = collect($groupIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            return ['second' => [], 'third' => []];
        }

        $secondIds = self::visibleSecondGroups()
            ->whereIn('second_product_groups.id', $normalized)
            ->pluck('second_product_groups.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $thirdIds = self::visibleThirdGroups()
            ->whereIn('third_product_groups.id', $normalized)
            ->pluck('third_product_groups.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return ['second' => $secondIds, 'third' => $thirdIds];
    }

    private static function normalizeCode(?string $code): string
    {
        return trim((string) $code);
    }
}
