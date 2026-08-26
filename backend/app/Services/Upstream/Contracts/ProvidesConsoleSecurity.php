<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游自定义模块/安全能力（软契约）。
 *
 * @method string fetchCustomModulePage(\App\Models\Supplier $supplier, int $hostId, string $moduleKey, ?string $jwt = null)
 * @method array submitCustomModuleAction(\App\Models\Supplier $supplier, string $endpoint, array $payload, ?string $jwt = null)
 * @method string getCustomModuleActionEndpoint(\App\Models\Supplier $supplier, int $hostId)
 */
interface ProvidesConsoleSecurity {}
