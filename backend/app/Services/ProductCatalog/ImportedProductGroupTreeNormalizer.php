<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog;

use App\Constants\ProductType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportedProductGroupTreeNormalizer
{
    /**
     * Convert the legacy two-level product_groups dump into the current type/root/leaf tree.
     *
     * @return array{created_first_group_count: int, reparented_root_count: int, promoted_child_count: int, skipped_root_count: int}
     */
    public function normalize(): array
    {
        $result = [
            'created_first_group_count' => 0,
            'reparented_root_count' => 0,
            'promoted_child_count' => 0,
            'skipped_root_count' => 0,
        ];

        if (! Schema::hasTable('product_groups')) {
            return $result;
        }

        $typeItems = collect(ProductType::items())
            ->mapWithKeys(function (array $item): array {
                $code = trim((string) ($item['value'] ?? ''));

                return $code === '' ? [] : [$code => $item];
            });

        if ($typeItems->isEmpty()) {
            return $result;
        }

        return DB::transaction(function () use ($result, $typeItems): array {
            $legacyRoots = DB::table('product_groups')
                ->select(['id', 'product_type'])
                ->whereNull('parent_id')
                ->where('level', 1)
                ->where(function ($query): void {
                    $query->whereNull('code')->orWhere('code', '');
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($legacyRoots as $legacyRoot) {
                $typeCode = trim((string) $legacyRoot->product_type);
                $item = $typeItems->get($typeCode);
                if (! is_array($item)) {
                    $result['skipped_root_count']++;

                    continue;
                }

                [$firstGroupId, $created] = $this->firstGroupId($typeCode, $item);
                if ($created) {
                    $result['created_first_group_count']++;
                }

                $result['promoted_child_count'] += DB::table('product_groups')
                    ->where('parent_id', (int) $legacyRoot->id)
                    ->where('level', 2)
                    ->update(['level' => 3, 'updated_at' => now()]);

                DB::table('product_groups')
                    ->where('id', (int) $legacyRoot->id)
                    ->update([
                        'parent_id' => $firstGroupId,
                        'level' => 2,
                        'updated_at' => now(),
                    ]);
                $result['reparented_root_count']++;
            }

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{0: int, 1: bool}
     */
    private function firstGroupId(string $code, array $item): array
    {
        $existingId = DB::table('product_groups')
            ->whereNull('parent_id')
            ->where('level', 1)
            ->where('code', $code)
            ->value('id');

        if ($existingId !== null) {
            return [(int) $existingId, false];
        }

        $now = now();
        $id = DB::table('product_groups')->insertGetId([
            'parent_id' => null,
            'level' => 1,
            'code' => $code,
            'product_type' => ProductType::normalizeBusinessValue($item['product_type'] ?? $code),
            'name' => trim((string) ($item['label'] ?? $code)),
            'slug' => $this->uniqueRootSlug($code),
            'description' => null,
            'icon' => trim((string) ($item['icon'] ?? '')) ?: null,
            'banner_image' => null,
            'sort_order' => (int) ($item['internal_id'] ?? 999),
            'is_visible' => (bool) ($item['is_hidden'] ?? false) ? 0 : 1,
            'is_system' => (bool) ($item['is_builtin'] ?? false) ? 1 : 0,
            'legacy_product_type' => $code,
            'legacy_product_group_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [(int) $id, true];
    }

    private function uniqueRootSlug(string $code): string
    {
        $base = str_replace('_', '-', $code);
        $slug = $base;
        $suffix = 2;

        while (DB::table('product_groups')->whereNull('parent_id')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
