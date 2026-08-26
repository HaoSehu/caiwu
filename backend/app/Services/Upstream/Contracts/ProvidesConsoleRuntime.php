<?php

declare(strict_types=1);

namespace App\Services\Upstream\Contracts;

/**
 * 上游实例运行时能力：详情、VNC、电源、重装、监控图表（软契约）。
 *
 * @method array getHostDetail(\App\Models\Supplier $supplier, int $hostId, ?string $jwt = null)
 * @method array getVncUrl(\App\Models\Supplier $supplier, int $hostId, ?string $jwt = null)
 * @method array powerAction(\App\Models\Supplier $supplier, int $hostId, string $action, ?string $jwt = null)
 * @method array getModuleStatus(\App\Models\Supplier $supplier, int $hostId, string $type = 'host', ?string $jwt = null)
 * @method array getReinstallOptions(\App\Models\Supplier $supplier, int $hostId, ?string $jwt = null)
 * @method array resetPassword(\App\Models\Supplier $supplier, int $hostId, string $password, ?string $jwt = null)
 * @method array reinstall(\App\Models\Supplier $supplier, int $hostId, string $osId, ?string $jwt = null)
 * @method array getSupportedModules(\App\Models\Supplier $supplier, int $hostId, ?string $jwt = null)
 * @method array getMonitorChart(\App\Models\Supplier $supplier, int $hostId, array $query, ?string $jwt = null)
 * @method array getMonitorCharts(\App\Models\Supplier $supplier, int $hostId, array $queries, ?string $jwt = null)
 */
interface ProvidesConsoleRuntime {}
