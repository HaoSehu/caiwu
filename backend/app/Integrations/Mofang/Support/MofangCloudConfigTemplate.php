<?php

declare(strict_types=1);

namespace App\Integrations\Mofang\Support;

use App\Services\Upstream\Support\CloudConfigTemplate;

final class MofangCloudConfigTemplate extends CloudConfigTemplate
{
    public function __construct(
        private readonly MofangProductTypeMapper $productTypeMapper = new MofangProductTypeMapper,
    ) {}

    public function supports(array $product): bool
    {
        return $this->productTypeMapper->supportsCloudTemplate($product);
    }

    protected function source(): string
    {
        return 'mofang_api';
    }
}
