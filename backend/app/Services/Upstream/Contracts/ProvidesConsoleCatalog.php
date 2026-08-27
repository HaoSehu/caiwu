<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游商品目录与配置项能力（软契约）。
 *
 * @method array getProductCatalog(\App\Models\Supplier $supplier)
 * @method array getProductCatalogTree(\App\Models\Supplier $supplier) 轻量目录（不含价格详情），列表场景按需实现
 * @method array getProductConfigTemplate(\App\Models\Supplier $supplier, int $productId)
 * @method array fetchRealConfigOptions(\App\Models\Supplier $supplier, int $productId)
 * @method array fetchBatchProductConfigOptions(\App\Models\Supplier $supplier, array $productIds, int $chunkSize = 8)
 * @method array fetchBatchProductStocks(\App\Models\Supplier $supplier, array $productIds, int $chunkSize = 8)
 */
interface ProvidesConsoleCatalog {}
