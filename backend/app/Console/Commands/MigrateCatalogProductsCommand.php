<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\CatalogMigrationService;

class MigrateCatalogProductsCommand extends CatalogMigrateBaseCommand
{
    protected $signature = 'migrate:catalog:products
        {--dry-run : 只输出统计信息，不写入新库}
        {--plan : 输出更详细的迁移计划}
        {--force : 忽略幂等保护，强制重新迁移}
        {--batch-size=500 : 每批迁移行数}
        {--json : 以 JSON 输出结果}';

    protected $description = '迁移旧库 products 到新库 idc.products';

    protected function sourceTable(): string
    {
        return 'products';
    }

    protected function targetTable(): string
    {
        return 'products';
    }

    protected function migrationName(): string
    {
        return 'catalog_products';
    }

    protected function preCheck(CatalogMigrationService $service): ?array
    {
        $rows = $service->sourceQuery('SELECT COUNT(*) AS cnt FROM products WHERE product_group_id IS NULL');
        $supplierRows = $service->sourceQuery('SELECT COUNT(*) AS cnt FROM products WHERE supplier_id IS NOT NULL');

        return [
            '无分组商品数' => (int) ($rows[0]->cnt ?? 0),
            '带供应商商品数' => (int) ($supplierRows[0]->cnt ?? 0),
        ];
    }

    protected function migrateRows(array $commonColumns, int $batchSize): int
    {
        $service = $this->laravel->make(CatalogMigrationService::class);

        // 收集旧库产品组 ID
        $legacyGroupIds = [];
        foreach ($service->sourceTableRows('product_groups') as $group) {
            $legacyGroupIds[(int) $group['id']] = true;
        }

        // 收集新库有效产品组 ID
        $validGroupIds = [];
        foreach ($service->targetQuery('SELECT id FROM product_groups') as $row) {
            $validGroupIds[(int) $row->id] = true;
        }

        $groups = [];
        foreach ($service->sourceTableRows('product_groups') as $group) {
            $groups[(int) $group['id']] = $group;
        }

        $usedSlugs = [];
        foreach ($service->targetQuery('SELECT slug FROM products') as $row) {
            $usedSlugs[(string) $row->slug] = true;
        }

        $total = 0;
        $offset = 0;

        do {
            $rows = $service->sourcePaginate('products', $offset, $batchSize);
            if ($rows === []) {
                break;
            }

            $payloads = [];
            foreach ($rows as $row) {
                $legacyProduct = (array) $row;
                $groupId = isset($legacyProduct['product_group_id']) ? (int) $legacyProduct['product_group_id'] : 0;

                // 只有当产品组 ID 同时存在于旧库和新库时才设置，否则设为 null
                $resolvedGroupId = ($groupId > 0 && isset($legacyGroupIds[$groupId]) && isset($validGroupIds[$groupId]))
                    ? $groupId
                    : null;

                $payload = $service->buildProductPayload(
                    $legacyProduct,
                    $resolvedGroupId !== null ? ($groups[$groupId] ?? null) : null,
                    $usedSlugs,
                    $resolvedGroupId
                );
                $usedSlugs[$payload['slug']] = true;
                $payloads[] = $payload;
            }

            $columns = array_keys($payloads[0]);
            $total += $service->batchUpsert(
                'products',
                $columns,
                $payloads,
                ['id'],
                array_values(array_diff($columns, ['id']))
            );

            $offset += count($rows);
            $this->line("  已处理 {$offset} 行...");
        } while (count($rows) === $batchSize);

        return $total;
    }
}
