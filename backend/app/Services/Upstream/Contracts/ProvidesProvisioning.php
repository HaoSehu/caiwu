<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游开通能力（软契约）。
 *
 * @method array provisionOrder(\App\Models\Order $order, \App\Models\Supplier $supplier, ?\App\Models\Service $existingService = null)
 * @method array getProductProvisionConfig(\App\Models\Supplier $supplier, int $productId)
 */
interface ProvidesProvisioning {}
