<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ProductCatalog\ImportedProductGroupTreeNormalizer;
use Illuminate\Console\Command;

class NormalizeImportedProductGroupTreeCommand extends Command
{
    protected $signature = 'product-catalog:normalize-imported-product-group-tree';

    protected $description = '将旧数据导入的两层 product_groups 规范为当前三级商品树';

    public function handle(ImportedProductGroupTreeNormalizer $normalizer): int
    {
        $this->line(json_encode($normalizer->normalize(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
