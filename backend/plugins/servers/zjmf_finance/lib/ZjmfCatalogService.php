<?php

declare(strict_types=1);

namespace Caiwu\Plugins\Servers\ZjmfFinance\Lib;

use App\Exceptions\BusinessException;
use App\Models\Supplier;
use App\Services\Upstream\Support\UpstreamConfigOptionNormalizer;
use Illuminate\Support\Facades\Log;

final class ZjmfCatalogService
{
    public function __construct(
        private readonly ZjmfFinanceTransport $transport,
        private readonly ZjmfCloudConfigTemplate $cloudConfigTemplate,
        private readonly ZjmfProductTypeMapper $productTypeMapper = new ZjmfProductTypeMapper,
    ) {}

    /**
     * 轻量目录：仅 /cart/all 商品树，不合并价格详情，供列表/级联展示按需使用。
     */
    public function getProductCatalogTree(Supplier $supplier): array
    {
        $response = $this->transport->get($supplier, '/cart/all', $this->transport->login($supplier));

        return $this->normalizeProductCatalog($response);
    }

    public function getProductCatalog(Supplier $supplier): array
    {
        $catalog = $this->getProductCatalogTree($supplier);
        $catalog['products'] = $this->mergeUpstreamProductPricing($supplier, $catalog['products']);

        return $catalog;
    }

    public function getProductConfigTemplate(Supplier $supplier, int $productId): array
    {
        // 模板只需单个商品：目录树定位商品，配置项按商品 id 单独获取。
        $catalog = $this->getProductCatalogTree($supplier);
        $product = collect($catalog['products'] ?? [])->first(
            fn (array $item) => (int) ($item['id'] ?? 0) === $productId
        );

        if (! is_array($product)) {
            throw new BusinessException('未找到对应的供应商商品', 40400);
        }

        if (! $this->cloudConfigTemplate->supports($product)) {
            throw new BusinessException('当前供应商商品类型暂不支持自动拉取配置项', 42200);
        }

        $configOptions = $this->fetchRealConfigOptions($supplier, $productId);
        if (empty($configOptions)) {
            $configOptions = collect($this->cloudConfigTemplate->build($product))
                ->filter(fn (array $item) => trim((string) ($item['parameter'] ?? '')) !== '')
                ->values()
                ->all();
        }

        $autoFilledFields = collect($configOptions)
            ->map(fn ($item) => $item['field'] ?? $item['option_name'] ?? '')
            ->filter()
            ->values()
            ->all();

        return [
            'product' => $product,
            'config_options' => $configOptions,
            'auto_filled_fields' => $autoFilledFields,
        ];
    }

    public function fetchRealConfigOptions(Supplier $supplier, int $productId): array
    {
        $response = $this->transport->get(
            $supplier,
            '/cart/get_product_config',
            $this->transport->login($supplier),
            ['pid' => $productId],
        );

        return UpstreamConfigOptionNormalizer::normalizeRemoteConfigOptions(
            $this->extractStorefrontConfigOptionsFromResponse($response)
        );
    }

    public function fetchBatchProductConfigOptions(Supplier $supplier, array $productIds, int $chunkSize = 8): array
    {
        $ids = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $chunkSize = max(1, min($chunkSize, 12));
        $jwt = $this->transport->login($supplier);
        $results = [];

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            $responses = $this->transport->parallelGet(
                $supplier,
                collect($chunk)->mapWithKeys(fn (int $productId) => [
                    (string) $productId => [
                        'uri' => '/cart/get_product_config',
                        'query' => ['pid' => $productId],
                    ],
                ])->all(),
                $jwt,
            );

            foreach ($chunk as $productId) {
                $response = $responses[(string) $productId]['response'] ?? null;
                $storefrontConfigOptions = $this->extractStorefrontConfigOptionsFromResponse($response);
                $results[$productId] = $storefrontConfigOptions !== []
                    ? UpstreamConfigOptionNormalizer::normalizeRemoteConfigOptions($storefrontConfigOptions)
                    : [];
            }
        }

        return $results;
    }

    public function fetchBatchProductStocks(Supplier $supplier, array $productIds, int $chunkSize = 8): array
    {
        $ids = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        // 批量明细接口一次覆盖全部商品（prodetail 单批最多 500 个），
        // 仅对明细缺失的商品回退逐商品配置接口，避免逐商品拉取。
        $results = $this->fetchProductStocksFromDetails($supplier, $ids);
        $missingIds = collect($ids)
            ->filter(fn (int $productId) => ! is_array($results[$productId] ?? null))
            ->values()
            ->all();

        if ($missingIds !== []) {
            $chunkSize = max(1, min($chunkSize, 12));
            $jwt = $this->transport->login($supplier);

            foreach (array_chunk($missingIds, $chunkSize) as $chunk) {
                $responses = $this->transport->parallelGet(
                    $supplier,
                    collect($chunk)->mapWithKeys(fn (int $productId) => [
                        (string) $productId => [
                            'uri' => '/cart/get_product_config',
                            'query' => ['pid' => $productId],
                        ],
                    ])->all(),
                    $jwt,
                );

                foreach ($chunk as $productId) {
                    $response = $responses[(string) $productId]['response'] ?? null;
                    $results[$productId] = is_array($response)
                        ? $this->extractLegacyProductStock($response, $productId)
                        : null;
                }
            }
        }

        return $results;
    }

    /**
     * 从 prodetail 批量明细提取库存；未返回 stock_control 的商品视为缺失。
     *
     * @param  array<int, int>  $productIds
     * @return array<int, array<string, mixed>|null>
     */
    private function fetchProductStocksFromDetails(Supplier $supplier, array $productIds): array
    {
        $details = $this->fetchBatchProductDetails($supplier, $productIds);
        $results = [];

        foreach ($productIds as $productId) {
            $detail = $details[$productId] ?? null;
            if (! is_array($detail) || ! array_key_exists('stock_control', $detail)) {
                continue;
            }

            $results[$productId] = [
                'stock_control' => (int) ($detail['stock_control'] ?? 0),
                'qty' => is_numeric($detail['qty'] ?? null) ? max((int) $detail['qty'], 0) : null,
                'stock' => $this->normalizeCatalogStock($detail),
                'allow_qty' => (int) ($detail['allow_qty'] ?? 0),
            ];
        }

        return $results;
    }

    private function normalizeProductCatalog(array $response): array
    {
        $payload = is_array($response['data'] ?? null) ? $response['data'] : $response;
        $groupedProducts = [];
        $flatProducts = [];

        $this->collectCartCatalogProducts(
            is_array($payload['products'] ?? null) ? $payload['products'] : [],
            '',
            $groupedProducts,
            $flatProducts,
        );

        return [
            'groups' => collect($groupedProducts)
                ->map(fn (array $items, string $label): array => [
                    'key' => 'group-'.md5($label),
                    'label' => $label,
                    'items' => $items,
                ])
                ->values()
                ->all(),
            'products' => $flatProducts,
        ];
    }

    /**
     * @param  array<int, mixed>  $entries
     * @param  array<string, array<int, array<string, mixed>>>  $groupedProducts
     * @param  array<int, array<string, mixed>>  $flatProducts
     */
    private function collectCartCatalogProducts(
        array $entries,
        string $parentLabel,
        array &$groupedProducts,
        array &$flatProducts,
    ): void {
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            $children = is_array($entry['products'] ?? null) ? $entry['products'] : [];
            if ($children !== []) {
                $label = $name === ''
                    ? $parentLabel
                    : ($parentLabel === '' ? $name : "{$parentLabel} / {$name}");
                $this->collectCartCatalogProducts($children, $label, $groupedProducts, $flatProducts);

                continue;
            }

            $productId = (int) ($entry['id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $groupLabel = $parentLabel !== '' ? $parentLabel : '未分组';
            $item = $this->productTypeMapper->normalizeProduct([
                'id' => $productId,
                'name' => $name !== '' ? $name : '未命名商品',
                'type' => trim((string) ($entry['type'] ?? '')),
                'description' => trim((string) ($entry['description'] ?? '')),
                'billingcycle' => trim((string) ($entry['billingcycle'] ?? $entry['upstream_cycle'] ?? '')),
                'product_price' => $this->normalizeCatalogAmount(
                    $entry['product_price'] ?? $entry['upstream_price'] ?? $entry['price'] ?? null
                ),
                'monthly_price' => $this->normalizeCatalogAmount($entry['monthly'] ?? $entry['monthly_price'] ?? null),
                'setup_fee' => $this->normalizeCatalogAmount($entry['setup_fee'] ?? null),
                'allow_qty' => (int) ($entry['allow_qty'] ?? 0),
                'stock_control' => (int) ($entry['stock_control'] ?? 0),
                'qty' => is_numeric($entry['qty'] ?? null) ? max((int) $entry['qty'], 0) : null,
                'stock' => $this->normalizeCatalogStock($entry),
                'group_name' => $groupLabel,
                'group_label' => $groupLabel,
            ]);

            $groupedProducts[$groupLabel] ??= [];
            $groupedProducts[$groupLabel][] = $item;
            $flatProducts[] = $item;
        }
    }

    /**
     * /cart/all 不含价格与库存，使用 api/product/prodetail 的详情补齐。
     *
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, array<string, mixed>>
     */
    private function mergeUpstreamProductPricing(Supplier $supplier, array $products): array
    {
        $ids = collect($products)
            ->map(fn (array $item) => (int) ($item['id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return $products;
        }

        $details = $this->fetchBatchProductDetails($supplier, $ids);
        if ($details === []) {
            return $products;
        }

        return collect($products)
            ->map(function (array $item) use ($details): array {
                $detail = $details[(int) ($item['id'] ?? 0)] ?? null;

                return $this->applyUpstreamProductDetail($item, $detail);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $detail
     * @return array<string, mixed>
     */
    private function applyUpstreamProductDetail(array $item, ?array $detail): array
    {
        if (! is_array($detail)) {
            return $item;
        }

        $pricingRow = collect($detail['product_pricings'] ?? [])->first(fn ($pricing) => is_array($pricing));
        if (is_array($pricingRow)) {
            [$cycle, $price, $setupFee] = $this->resolveUpstreamPricingCycle($pricingRow);
            if ($cycle !== null) {
                $item['billingcycle'] = $cycle;
                $item['product_price'] = $price;
                $item['setup_fee'] = $setupFee;
            }

            $monthly = $this->normalizeCatalogNonNegativeAmount($pricingRow['monthly'] ?? null);
            if ($monthly !== null) {
                $item['monthly_price'] = $monthly;
            }
        }

        if (array_key_exists('stock_control', $detail)) {
            $item['stock_control'] = (int) ($detail['stock_control'] ?? 0);
        }

        if (array_key_exists('qty', $detail)) {
            $item['qty'] = is_numeric($detail['qty'] ?? null) ? max((int) $detail['qty'], 0) : null;
        }

        $item['stock'] = $this->normalizeCatalogStock($item);

        return $item;
    }

    /**
     * 上游 prodetail 单次最多返回 500 个商品，按批拉取。
     *
     * @param  array<int, int>  $productIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchBatchProductDetails(Supplier $supplier, array $productIds, int $chunkSize = 200): array
    {
        $chunkSize = max(1, min($chunkSize, 500));
        $jwt = $this->transport->login($supplier);
        $details = [];

        foreach (array_chunk($productIds, $chunkSize) as $chunk) {
            try {
                $response = $this->transport->get(
                    $supplier,
                    '/api/product/prodetail',
                    $jwt,
                    ['pids' => $chunk],
                );
            } catch (\Throwable $exception) {
                Log::warning('[ZJMF 商品目录] 拉取商品详情失败', [
                    'supplier_id' => $supplier->id,
                    'message' => $exception->getMessage(),
                ]);

                continue;
            }

            $payload = is_array($response['data'] ?? null) ? $response['data'] : [];
            if (! is_array($payload['detail'] ?? null)) {
                continue;
            }

            foreach ($payload['detail'] as $pid => $detail) {
                if (is_array($detail)) {
                    $details[(int) $pid] = $detail;
                }
            }
        }

        return $details;
    }

    /**
     * 与上游一致：按周期顺序取第一个有效价格（>= 0）作为展示单价与周期。
     *
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private function resolveUpstreamPricingCycle(array $pricingRow): array
    {
        $cycleKeys = [
            'hour' => 'hsetupfee',
            'day' => 'dsetupfee',
            'ontrial' => 'ontrialfee',
            'monthly' => 'msetupfee',
            'quarterly' => 'qsetupfee',
            'semiannually' => 'ssetupfee',
            'annually' => 'asetupfee',
            'biennially' => 'bsetupfee',
            'triennially' => 'tsetupfee',
            'fourly' => 'foursetupfee',
            'fively' => 'fivesetupfee',
            'sixly' => 'sixsetupfee',
            'sevenly' => 'sevensetupfee',
            'eightly' => 'eightsetupfee',
            'ninely' => 'ninesetupfee',
            'tenly' => 'tensetupfee',
            'onetime' => 'osetupfee',
        ];

        foreach ($cycleKeys as $cycle => $setupFeeKey) {
            $price = $this->normalizeCatalogNonNegativeAmount($pricingRow[$cycle] ?? null);
            if ($price === null) {
                continue;
            }

            return [
                $cycle,
                $price,
                $this->normalizeCatalogNonNegativeAmount($pricingRow[$setupFeeKey] ?? null),
            ];
        }

        return [null, null, null];
    }

    private function normalizeCatalogNonNegativeAmount(mixed $value): ?string
    {
        $amount = $this->normalizeCatalogAmount($value);

        return $amount !== null && (float) $amount >= 0 ? $amount : null;
    }

    private function extractLegacyProductStock(array $response, int $productId): ?array
    {
        $product = $response['data']['products'] ?? null;
        if (! is_array($product) || (int) ($product['id'] ?? 0) !== $productId) {
            return null;
        }

        return [
            'stock_control' => (int) ($product['stock_control'] ?? 0),
            'qty' => is_numeric($product['qty'] ?? null) ? max((int) $product['qty'], 0) : null,
            'stock' => $this->normalizeCatalogStock($product),
            'allow_qty' => (int) ($product['allow_qty'] ?? 0),
        ];
    }

    private function extractStorefrontConfigOptionsFromResponse(?array $response): array
    {
        if (! is_array($response) || (int) ($response['status'] ?? 0) !== 200) {
            return [];
        }

        $data = $response['data'] ?? null;
        if (! is_array($data)) {
            return [];
        }

        return collect($data['config_groups'] ?? [])
            ->filter(fn ($group) => is_array($group))
            ->flatMap(function (array $group) {
                return is_array($group['options'] ?? null) ? $group['options'] : [];
            })
            ->filter(fn ($item) => is_array($item))
            ->values()
            ->all();
    }

    // 配置项归一化实现已收敛到平台 UpstreamConfigOptionNormalizer（D3B-04），插件直接静态调用。

    private function normalizeCatalogStock(array $product): int
    {
        if ((int) ($product['stock_control'] ?? 0) !== 1) {
            return -1;
        }

        $qty = $product['qty'] ?? null;
        if ($qty === null || $qty === '' || ! is_numeric($qty)) {
            return 0;
        }

        return max((int) $qty, 0);
    }

    private function normalizeCatalogAmount(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
