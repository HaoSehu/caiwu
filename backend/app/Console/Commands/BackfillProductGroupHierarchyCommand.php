<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ProductCatalog\ProductGroupHierarchyService;
use Illuminate\Console\Command;

class BackfillProductGroupHierarchyCommand extends Command
{
    protected $signature = 'product-catalog:backfill-product-group-hierarchy
        {--dry-run : 只统计影响范围，不写入数据}
        {--chunk=500 : 分块处理数量}';

    protected $description = '从旧 product_groups/product_group_id 回填三层商品分类表和商品挂载字段';

    public function handle(ProductGroupHierarchyService $service): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $result = $service->syncAllFromLegacy($chunkSize, $dryRun);

        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if (! ($result['tables_ready'] ?? false)) {
            $this->error('三层商品分类表尚未创建，请先执行数据库迁移');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
