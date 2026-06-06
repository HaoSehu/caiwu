<?php

declare(strict_types=1);

namespace App\Integrations\Mofang\Adapters;

use App\Exceptions\BusinessException;
use App\Integrations\Mofang\Support\MofangCloudConfigTemplate;
use App\Integrations\Mofang\Support\MofangProductTypeMapper;
use App\Models\Supplier;
use App\Services\Upstream\Contracts\ProvidesConsoleAccess;
use App\Services\Upstream\Contracts\ProvidesConsoleCatalog;
use App\Services\Upstream\Contracts\ProvidesConsoleNetwork;
use App\Services\Upstream\Contracts\ProvidesConsoleRuntime;
use App\Services\Upstream\Contracts\ProvidesConsoleSecurity;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\Contracts\ProvidesScheduledAuthRefresh;
use App\Services\Upstream\Contracts\ProvidesStatusSync;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use BadMethodCallException;

final class MofangFinanceAdapter implements ProvidesConsoleAccess, ProvidesConsoleCatalog, ProvidesConsoleNetwork, ProvidesConsoleRuntime, ProvidesConsoleSecurity, ProvidesProvisioning, ProvidesRenewal, ProvidesScheduledAuthRefresh, ProvidesStatusSync
{
    public function __construct(
        private readonly HostingPanelApiTransport $transport,
        private readonly MofangCloudConfigTemplate $cloudConfigTemplate,
        private readonly MofangProductTypeMapper $productTypeMapper = new MofangProductTypeMapper,
    ) {}

    public function getProductCatalog(Supplier $supplier): array
    {
        $catalog = $this->transport->getProductCatalog($supplier);

        if (is_array($catalog['products'] ?? null)) {
            foreach ($catalog['products'] as $index => $product) {
                if (is_array($product)) {
                    $catalog['products'][$index] = $this->productTypeMapper->normalizeProduct($product);
                }
            }
        }

        if (is_array($catalog['groups'] ?? null)) {
            foreach ($catalog['groups'] as $groupIndex => $group) {
                if (! is_array($group) || ! is_array($group['items'] ?? null)) {
                    continue;
                }

                foreach ($group['items'] as $itemIndex => $item) {
                    if (is_array($item)) {
                        $catalog['groups'][$groupIndex]['items'][$itemIndex] = $this->productTypeMapper->normalizeProduct($item);
                    }
                }
            }
        }

        return $catalog;
    }

    public function getProductConfigTemplate(Supplier $supplier, int $productId): array
    {
        $catalog = $this->getProductCatalog($supplier);
        $product = collect($catalog['products'] ?? [])->first(
            fn (array $item) => (int) ($item['id'] ?? 0) === $productId
        );

        if (! is_array($product)) {
            throw new BusinessException('未找到对应的供应商商品', 40400);
        }

        if (! $this->cloudConfigTemplate->supports($product)) {
            throw new BusinessException('当前供应商商品类型暂不支持自动拉取配置项', 42200);
        }

        $configOptions = $this->transport->fetchRealConfigOptions($supplier, $productId);
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

    public function __call(string $method, array $parameters): mixed
    {
        if (! is_callable([$this->transport, $method])) {
            throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', self::class, $method));
        }

        return $this->transport->{$method}(...$parameters);
    }
}
