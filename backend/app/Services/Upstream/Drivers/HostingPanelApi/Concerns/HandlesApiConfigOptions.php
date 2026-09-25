<?php

declare(strict_types=1);

namespace App\Services\Upstream\Drivers\HostingPanelApi\Concerns;

use App\Exceptions\BusinessException;
use App\Models\Supplier;
use App\Services\Upstream\Support\UpstreamConfigOptionNormalizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

trait HandlesApiConfigOptions
{
    // 配置项模板缓存：目录 + /v1/productsconfig + 前台回退最多 3 次串行上游调用，
    // 用户购买路径每次拉取代价过高；配置项变更频率低，10 分钟陈旧度可接受。
    // 同步、管理端等需要实时数据的调用方传 bypassCache = true。
    private const PRODUCT_CONFIG_TEMPLATE_CACHE_TTL_SECONDS = 600;

    public function getProductConfigTemplate(Supplier $supplier, int $productId, bool $bypassCache = false): array
    {
        $cacheKey = 'upstream:hosting_panel_api:config_template:'.$supplier->id.':'.$productId;
        $store = Cache::store('redis_volatile');

        if (! $bypassCache) {
            $cached = $store->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $template = $this->resolveProductConfigTemplate($supplier, $productId);

        $store->put($cacheKey, $template, now()->addSeconds(self::PRODUCT_CONFIG_TEMPLATE_CACHE_TTL_SECONDS));

        return $template;
    }

    private function resolveProductConfigTemplate(Supplier $supplier, int $productId): array
    {
        $catalog = $this->getProductCatalog($supplier);
        $product = collect($catalog['products'] ?? [])->first(
            fn (array $item) => (int) ($item['id'] ?? 0) === $productId
        );

        if (! is_array($product)) {
            throw new BusinessException('未找到对应的供应商商品', 40400);
        }

        if (! $this->supportsConfigTemplate($product)) {
            throw new BusinessException('当前供应商商品类型暂不支持自动拉取配置项', 42200);
        }

        // 优先从 /v1/productsconfig 拉取真实配置项
        $configOptions = $this->fetchRealConfigOptions($supplier, $productId);

        // 若接口无数据则降级到正则提取
        if (empty($configOptions)) {
            $configOptions = collect($this->buildCloudConfigTemplate($product))
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

    /**
     * 从官方 /v1/productsconfig 拉取真实配置项；若上游缺失或返回空，再回退到前台页面接口。
     */
    public function fetchRealConfigOptions(Supplier $supplier, int $productId): array
    {
        $jwt = $this->login($supplier);
        $response = $this->get($supplier, '/v1/productsconfig', $jwt, ['product_id' => $productId]);
        $officialConfigOptions = $this->normalizeRemoteConfigOptions($this->extractApiConfigOptions($response, $productId));

        if ($officialConfigOptions !== []) {
            return $officialConfigOptions;
        }

        $storefrontConfigOptions = $this->fetchStorefrontConfigOptions($supplier, $productId);
        if ($storefrontConfigOptions !== []) {
            return $this->normalizeRemoteConfigOptions($storefrontConfigOptions);
        }

        return [];
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
        $rootUrl = $this->resolveSupplierRootUrl($supplier);
        $results = [];

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            $responses = $this->parallelGet(
                $supplier,
                collect($chunk)->mapWithKeys(fn (int $productId) => [
                    (string) $productId => [
                        'uri' => $rootUrl.'/cart/get_product_config',
                        'query' => ['pid' => $productId],
                    ],
                ])->all()
            );

            foreach ($chunk as $productId) {
                $response = $responses[(string) $productId]['response'] ?? null;
                $storefrontConfigOptions = $this->extractStorefrontConfigOptionsFromResponse($response);
                $results[$productId] = $storefrontConfigOptions !== []
                    ? $this->normalizeRemoteConfigOptions($storefrontConfigOptions)
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

        $chunkSize = max(1, min($chunkSize, 12));
        $jwt = $this->login($supplier);
        $results = [];

        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            $responses = $this->parallelGet(
                $supplier,
                collect($chunk)->mapWithKeys(fn (int $productId) => [
                    (string) $productId => [
                        'uri' => '/v1/productsconfig',
                        'query' => ['product_id' => $productId],
                    ],
                ])->all(),
                $jwt
            );

            foreach ($chunk as $productId) {
                $response = $responses[(string) $productId]['response'] ?? null;
                $results[$productId] = is_array($response)
                    ? $this->extractApiProductStock($response, $productId)
                    : null;
            }
        }

        return $results;
    }

    private function fetchStorefrontConfigOptions(Supplier $supplier, int $productId): array
    {
        try {
            $response = $this->get(
                $supplier,
                $this->resolveSupplierRootUrl($supplier).'/cart/get_product_config',
                null,
                ['pid' => $productId]
            );
        } catch (\Throwable $exception) {
            Log::info('[主机面板接口] 前台配置项接口不可用，回退到开放 API', [
                'supplier_id' => $supplier->id,
                'product_id' => $productId,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        return $this->extractStorefrontConfigOptionsFromResponse($response);
    }

    private function resolveSupplierRootUrl(Supplier $supplier): string
    {
        $baseUrl = trim((string) $supplier->api_url);
        $parts = parse_url($baseUrl);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return rtrim($baseUrl, '/');
        }

        $rootUrl = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $rootUrl .= ':'.$parts['port'];
        }

        return $rootUrl;
    }

    private function extractApiConfigOptions(array $response, int $productId): array
    {
        $product = $this->extractApiProductData($response, $productId);

        if (is_array($product)) {
            return is_array($product['configoptions'] ?? null) ? $product['configoptions'] : [];
        }

        return [];
    }

    private function extractApiProductStock(array $response, int $productId): ?array
    {
        $product = $this->extractApiProductData($response, $productId);
        if (! is_array($product)) {
            return null;
        }

        return [
            'stock_control' => (int) ($product['stock_control'] ?? 0),
            'qty' => is_numeric($product['qty'] ?? null) ? max((int) $product['qty'], 0) : null,
            'stock' => $this->normalizeCatalogStock($product),
            'allow_qty' => (int) ($product['allow_qty'] ?? 0),
        ];
    }

    private function extractApiProductData(array $response, int $productId): ?array
    {
        foreach ($response['data']['first_group'] ?? [] as $firstGroup) {
            foreach ($firstGroup['group'] ?? [] as $group) {
                foreach ($group['products'] ?? [] as $product) {
                    if ((int) ($product['id'] ?? 0) === $productId) {
                        return is_array($product) ? $product : null;
                    }
                }
            }
        }

        return null;
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

    // ── 配置项归一化：实现收敛到 UpstreamConfigOptionNormalizer（D3B-04），
    //    平台驱动与 zjmf_finance 插件共用同一实现，保留私有方法签名以兼容宿主类调用。 ──

    private function normalizeRemoteConfigOptions(array $configOptions): array
    {
        return UpstreamConfigOptionNormalizer::normalizeRemoteConfigOptions($configOptions);
    }

    private function normalizeRemoteConfigSubOptions(mixed $subOptions, int $optionType): array
    {
        return UpstreamConfigOptionNormalizer::normalizeRemoteConfigSubOptions($subOptions, $optionType);
    }

    private function parseRemoteSubOptionName(string $rawOptionName, int $optionType, string $fallbackValue = ''): array
    {
        return UpstreamConfigOptionNormalizer::parseRemoteSubOptionName($rawOptionName, $optionType, $fallbackValue);
    }

    private function normalizeRemoteSubPricing(mixed $pricing): array
    {
        return UpstreamConfigOptionNormalizer::normalizeRemoteSubPricing($pricing);
    }

    private function buildRemoteConfigOptionParameter(array $subOptions, int $optionType): string
    {
        return UpstreamConfigOptionNormalizer::buildRemoteConfigOptionParameter($subOptions, $optionType);
    }

    private function resolveConfigOptionField(array $item, ?string $mappedField, string $nameField, string $displayName): string
    {
        return UpstreamConfigOptionNormalizer::resolveConfigOptionField($item, $mappedField, $nameField, $displayName);
    }
}
