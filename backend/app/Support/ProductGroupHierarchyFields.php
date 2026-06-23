<?php

declare(strict_types=1);

namespace App\Support;

use App\Constants\ProductType;
use App\Models\FirstProductGroup;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use Illuminate\Support\Facades\Schema;

class ProductGroupHierarchyFields
{
    /**
     * @var array<string, bool>
     */
    private static array $tableExists = [];

    /**
     * @var array<string, FirstProductGroup|null>
     */
    private static array $firstByCode = [];

    /**
     * @var array<int, SecondProductGroup|null>
     */
    private static array $secondByLegacyId = [];

    /**
     * @var array<int, ThirdProductGroup|null>
     */
    private static array $thirdByLegacyId = [];

    public static function fromLegacyCategory(?ProductCategory $category): array
    {
        if (! $category instanceof ProductCategory) {
            return self::empty();
        }

        $parent = $category->relationLoaded('parent') ? $category->parent : null;
        $isThird = $parent instanceof ProductCategory;
        $firstCode = trim((string) (($isThird ? $parent->product_type : $category->product_type) ?? ''));
        $first = self::firstGroupByCode($firstCode);
        $secondLegacyCategory = $isThird ? $parent : $category;
        $second = self::secondGroupByLegacyId((int) $secondLegacyCategory->id);
        $third = $isThird ? self::thirdGroupByLegacyId((int) $category->id) : null;
        $firstId = $first instanceof FirstProductGroup ? (int) $first->id : null;
        $firstName = $first instanceof FirstProductGroup
            ? (string) $first->name
            : ProductType::labelOf($firstCode);
        $secondId = $second instanceof SecondProductGroup ? (int) $second->id : null;
        $thirdId = $third instanceof ThirdProductGroup ? (int) $third->id : null;

        return [
            'first_product_group_id' => $firstId,
            'first_product_group_code' => $firstCode,
            'first_product_group_name' => $firstName,
            'second_product_group_id' => $secondId,
            'second_product_group_name' => (string) ($secondLegacyCategory->name ?? ''),
            'second_product_group_parent_id' => $firstId,
            'second_product_group_parent_name' => $firstName,
            'third_product_group_id' => $thirdId,
            'third_product_group_name' => $isThird ? (string) ($category->name ?? '') : null,
            'effective_product_group_id' => $isThird ? $thirdId : $secondId,
            'effective_product_group_level' => $isThird ? 3 : 2,
        ];
    }

    public static function fromProductCategory(
        ?ProductCategory $category,
        ?int $firstProductGroupId,
        ?int $secondProductGroupId,
        ?int $thirdProductGroupId,
        ?string $serviceTypeCode = null,
    ): array {
        $fields = self::fromLegacyCategory($category);

        if ($firstProductGroupId !== null && $firstProductGroupId > 0) {
            $fields['first_product_group_id'] = $firstProductGroupId;
        }

        if ($secondProductGroupId !== null && $secondProductGroupId > 0) {
            $fields['second_product_group_id'] = $secondProductGroupId;
        }

        if ($thirdProductGroupId !== null && $thirdProductGroupId > 0) {
            $fields['third_product_group_id'] = $thirdProductGroupId;
            $fields['effective_product_group_id'] = $thirdProductGroupId;
            $fields['effective_product_group_level'] = 3;
        } elseif ($secondProductGroupId !== null && $secondProductGroupId > 0) {
            $fields['effective_product_group_id'] = $secondProductGroupId;
            $fields['effective_product_group_level'] = 2;
        }

        $fields['service_type_code'] = trim((string) ($serviceTypeCode ?? '')) ?: null;

        return $fields;
    }

    public static function fromProduct(Product $product): array
    {
        $first = $product->relationLoaded('firstProductGroup') ? $product->firstProductGroup : null;
        $second = $product->relationLoaded('secondProductGroup') ? $product->secondProductGroup : null;
        $third = $product->relationLoaded('thirdProductGroup') ? $product->thirdProductGroup : null;

        $firstId = $first instanceof FirstProductGroup
            ? (int) $first->id
            : ((int) ($product->getAttribute('first_product_group_id') ?? 0) ?: null);
        $secondId = $second instanceof SecondProductGroup
            ? (int) $second->id
            : ((int) ($product->getAttribute('second_product_group_id') ?? 0) ?: null);
        $thirdId = $third instanceof ThirdProductGroup
            ? (int) $third->id
            : ((int) ($product->getAttribute('third_product_group_id') ?? 0) ?: null);
        $serviceTypeCode = trim((string) ($product->getAttribute('service_type_code') ?: $product->getAttribute('product_type') ?: ''));
        $firstCode = trim((string) ($first?->code ?? $serviceTypeCode));
        $firstName = trim((string) ($first?->name ?? ProductType::labelOf($firstCode)));
        $secondName = trim((string) ($second?->name ?? ''));
        $thirdName = $thirdId !== null ? trim((string) ($third?->name ?? '')) : null;

        return [
            'first_product_group_id' => $firstId,
            'first_product_group_code' => $firstCode,
            'first_product_group_name' => $firstName,
            'second_product_group_id' => $secondId,
            'second_product_group_name' => $secondName,
            'second_product_group_parent_id' => $firstId,
            'second_product_group_parent_name' => $firstName,
            'third_product_group_id' => $thirdId,
            'third_product_group_name' => $thirdName,
            'effective_product_group_id' => $thirdId ?? $secondId,
            'effective_product_group_level' => $thirdId !== null ? 3 : ($secondId !== null ? 2 : null),
            'service_type_code' => $serviceTypeCode !== '' ? $serviceTypeCode : null,
        ];
    }

    private static function empty(): array
    {
        return [
            'first_product_group_id' => null,
            'first_product_group_code' => '',
            'first_product_group_name' => '',
            'second_product_group_id' => null,
            'second_product_group_name' => '',
            'second_product_group_parent_id' => null,
            'second_product_group_parent_name' => '',
            'third_product_group_id' => null,
            'third_product_group_name' => null,
            'effective_product_group_id' => null,
            'effective_product_group_level' => null,
        ];
    }

    private static function firstGroupByCode(string $code): ?FirstProductGroup
    {
        if ($code === '' || ! self::hasTable('first_product_groups')) {
            return null;
        }

        if (array_key_exists($code, self::$firstByCode)) {
            return self::$firstByCode[$code];
        }

        $group = FirstProductGroup::query()->where('code', $code)->first();
        if ($group instanceof FirstProductGroup) {
            self::$firstByCode[$code] = $group;
        }

        return $group;
    }

    private static function secondGroupByLegacyId(int $legacyGroupId): ?SecondProductGroup
    {
        if ($legacyGroupId <= 0 || ! self::hasTable('second_product_groups')) {
            return null;
        }

        if (array_key_exists($legacyGroupId, self::$secondByLegacyId)) {
            return self::$secondByLegacyId[$legacyGroupId];
        }

        $group = SecondProductGroup::query()
            ->where('legacy_product_group_id', $legacyGroupId)
            ->first();

        if ($group instanceof SecondProductGroup) {
            self::$secondByLegacyId[$legacyGroupId] = $group;
        }

        return $group;
    }

    private static function thirdGroupByLegacyId(int $legacyGroupId): ?ThirdProductGroup
    {
        if ($legacyGroupId <= 0 || ! self::hasTable('third_product_groups')) {
            return null;
        }

        if (array_key_exists($legacyGroupId, self::$thirdByLegacyId)) {
            return self::$thirdByLegacyId[$legacyGroupId];
        }

        $group = ThirdProductGroup::query()
            ->where('legacy_product_group_id', $legacyGroupId)
            ->first();

        if ($group instanceof ThirdProductGroup) {
            self::$thirdByLegacyId[$legacyGroupId] = $group;
        }

        return $group;
    }

    private static function hasTable(string $table): bool
    {
        if (! array_key_exists($table, self::$tableExists)) {
            self::$tableExists[$table] = Schema::hasTable($table);
        }

        return self::$tableExists[$table];
    }
}
