<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游服务状态同步能力（软契约）。
 *
 * @method array syncServiceStatuses(\App\Models\Supplier $supplier, array $items, int $chunkSize = 10)
 */
interface ProvidesStatusSync {}
