<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\System\CatalogMigrationService;
use Illuminate\Console\Command;

class ReconcileCatalogDomainCommand extends Command
{
    protected $signature = 'migrate:catalog:reconcile {--json : 以 JSON 输出结果}';

    protected $description = '执行商品目录与供应商域迁移后的总对账';

    public function handle(CatalogMigrationService $service): int
    {
        $service->ensureConnections();

        $legacyProducts = $service->sourceTableRows('products');
        $derived = $service->deriveCatalogMetrics($legacyProducts);

        $summary = [
            'product_groups' => [
                'old' => $service->sourceCount('product_groups'),
                'new' => $service->targetCount('product_groups'),
            ],
            'products' => [
                'old' => $service->sourceCount('products'),
                'new' => $service->targetCount('products'),
            ],
            'product_pricing_plans' => [
                'old_derived' => $derived['pricing_plan_count'],
                'new' => $service->targetCount('product_pricing_plans'),
            ],
            'product_config_options' => [
                'old_derived' => $derived['config_option_count'],
                'new' => $service->targetCount('product_config_options'),
            ],
            'suppliers' => [
                'old' => $service->sourceCount('suppliers'),
                'new' => $service->targetCount('suppliers'),
            ],
            'supplier_products' => [
                'old_derived' => $derived['supplier_product_count'],
                'new' => $service->targetCount('supplier_products'),
            ],
            'servers' => [
                'old' => $service->sourceCount('servers'),
                'new' => $service->targetCount('servers'),
            ],
        ];

        $orphans = [
            'products.product_group_id' => (int) (($service->targetQuery(
                'SELECT COUNT(*) AS cnt FROM products WHERE product_group_id IS NOT NULL AND product_group_id NOT IN (SELECT id FROM product_groups)'
            )[0]->cnt) ?? 0),
            'product_pricing_plans.product_id' => (int) (($service->targetQuery(
                'SELECT COUNT(*) AS cnt FROM product_pricing_plans WHERE product_id NOT IN (SELECT id FROM products)'
            )[0]->cnt) ?? 0),
            'product_config_options.product_id' => (int) (($service->targetQuery(
                'SELECT COUNT(*) AS cnt FROM product_config_options WHERE product_id NOT IN (SELECT id FROM products)'
            )[0]->cnt) ?? 0),
            'supplier_products.supplier_id' => (int) (($service->targetQuery(
                'SELECT COUNT(*) AS cnt FROM supplier_products WHERE supplier_id NOT IN (SELECT id FROM suppliers)'
            )[0]->cnt) ?? 0),
            'supplier_products.product_id' => (int) (($service->targetQuery(
                'SELECT COUNT(*) AS cnt FROM supplier_products WHERE product_id NOT IN (SELECT id FROM products)'
            )[0]->cnt) ?? 0),
        ];

        $payload = [
            'summary' => $summary,
            'orphans' => $orphans,
        ];

        if ((bool) $this->option('json')) {
            $this->line(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        foreach ($summary as $table => $counts) {
            $this->line($table.': '.json_encode($counts, JSON_UNESCAPED_UNICODE));
        }

        foreach ($orphans as $key => $count) {
            $this->line($key.' orphan='.$count);
        }

        return self::SUCCESS;
    }
}
