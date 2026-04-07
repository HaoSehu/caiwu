<?php

declare(strict_types=1);

namespace App\Services\ClientServiceConsole;

use App\Exceptions\BusinessException;
use App\Models\OperationLog;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\MofangFinanceClient;
use App\Services\OperationLogService;
use App\Support\ServiceHostname;
use App\Support\TextSanitizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 服务详情子服务
 * 负责：getServiceConfigForUser、getDetailForUser、getBaseDetailForUser、
 *       getRemoteStatusPatchForUser、getOperationLogsForUser、updateRemarkForUser
 *       以及 syncServiceFromRemote、fetchRemoteState 等远程同步方法
 */
class ServiceDetailService
{
    private const DETAIL_REMOTE_SNAPSHOT_TTL_SECONDS    = 120;
    private const DETAIL_RESPONSE_CACHE_TTL_SECONDS     = 20;
    private const SERVICE_CONFIG_CACHE_TTL_SECONDS      = 60;
    private const PRODUCT_CONFIG_OPTIONS_CACHE_TTL_SECONDS = 604800;
    private const MONITOR_MODULE_CACHE_TTL_SECONDS      = 600;

    public function __construct(
        private readonly MofangFinanceClient  $mofangFinanceClient,
        private readonly OperationLogService  $operationLogService,
        private readonly ServiceResolverService $resolverService,
        private readonly ServiceTransformService $transformService,
    ) {}

    // ── Public API ─────────────────────────────────────────────────────────

    public function getServiceConfigForUser(User $user, int $serviceId): array
    {
        $service = $this->findUserService($user, $serviceId, [
            'product:id,name,product_type',
            'product.categoryMapping:id,legacy_group_id,parent_id,product_type,name,title,slogan,slug',
            'product.categoryMapping.parent:id,legacy_group_id,parent_id,product_type,name,title,slogan,slug',
        ]);

        $cacheKey = $this->buildServiceConfigCacheKey($service);
        $cached   = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $provisionData      = (array) ($service->provision_data ?? []);
        $catalogProductType = $this->resolverService->resolveGroupedOverviewTypeValue($service);
        $consoleMode        = $this->resolverService->resolveConsoleMode($service, $provisionData);

        $payload = [
            'id'                 => $service->id,
            'name'               => $service->name,
            'status'             => (int) $service->status,
            'status_label'       => \App\Constants\ServiceStatus::$labels[$service->status] ?? (string) $service->status,
            'product_type'       => $catalogProductType,
            'product_type_label' => \App\Constants\ProductType::labelOf($catalogProductType),
            'machine_category'   => $this->transformService->resolveMachineCategory($service, $catalogProductType, $consoleMode),
            'console_mode'       => $consoleMode,
            'is_nat_console'     => $consoleMode === 'nat',
            'expires_at'         => $service->expires_at?->format('Y-m-d H:i:s'),
        ];

        Cache::put($cacheKey, $payload, now()->addSeconds(self::SERVICE_CONFIG_CACHE_TTL_SECONDS));

        return $payload;
    }

    public function getDetailForUser(User $user, int $serviceId, bool $refreshRemote = false): array
    {
        $service = $this->findUserService($user, $serviceId, [
            'product:id,name,product_type,supplier_id,provision_module,config_options,pricing',
            'product.supplier',
            'order:id,order_no,status,paid_at,created_at',
            'order.invoice:id,order_id,invoice_no',
        ]);

        $needsRemoteRefresh = $refreshRemote
            || $this->needsRemoteSnapshotRefresh($service)
            || $this->needsConnectionHydration($service)
            || $this->needsRuntimeStatusRefresh($service)
            || $this->needsNatRemoteHydration($service);

        if (! $needsRemoteRefresh) {
            $cacheKey = $this->buildDetailResponseCacheKey($service);
            $cached   = Cache::get($cacheKey);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        }

        $remote      = null;
        $remoteError = '';

        if ($needsRemoteRefresh && $this->transformService->canManageService($service)) {
            try {
                $remote = $this->fetchRemoteState($service);
                if (! empty($remote['host']) || ! empty($remote['runtime']) || ! empty($remote['nat'])) {
                    $this->syncServiceFromRemote($service, $remote['host'] ?? [], $remote['runtime'] ?? [], $remote['nat'] ?? []);
                    $service->refresh()->loadMissing([
                        'product:id,name,product_type,supplier_id,provision_module,config_options,pricing',
                        'product.categoryMapping:id,legacy_group_id,parent_id,product_type,name,title,slogan,slug',
                        'product.categoryMapping.parent:id,legacy_group_id,parent_id,product_type,name,title,slogan,slug',
                        'product.supplier',
                        'order:id,order_no,status,paid_at,created_at',
                        'order.invoice:id,order_id,invoice_no',
                    ]);
                }
            } catch (\Throwable $exception) {
                Log::warning('[服务详情] 远程状态刷新失败', [
                    'service_id' => (int) $service->id,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
                $remoteError = $exception->getMessage();
            }
        }

        $detail = $this->transformService->transformDetail($service, $remote, $remoteError);

        if ($remoteError === '') {
            Cache::put(
                $this->buildDetailResponseCacheKey($service),
                $detail,
                now()->addSeconds(self::DETAIL_RESPONSE_CACHE_TTL_SECONDS)
            );
        }

        return $detail;
    }

    public function getBaseDetailForUser(User $user, int $serviceId): array
    {
        $service = $this->findUserService($user, $serviceId, [
            'product:id,name,product_type,supplier_id,provision_module,config_options,pricing',
            'product.supplier',
            'order:id,order_no,status,paid_at,created_at',
            'order.invoice:id,order_id,invoice_no',
        ]);

        $cacheKey = $this->buildDetailResponseCacheKey($service);
        $cached   = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $detail = $this->transformService->transformDetail($service);
        Cache::put($cacheKey, $detail, now()->addSeconds(self::DETAIL_RESPONSE_CACHE_TTL_SECONDS));

        return $detail;
    }

    public function getRemoteStatusPatchForUser(User $user, int $serviceId): array
    {
        $service = $this->findUserService($user, $serviceId, [
            'product:id,name,product_type,supplier_id,provision_module,config_options,pricing',
            'product.supplier',
            'order:id,order_no,status,paid_at,created_at',
            'order.invoice:id,order_id,invoice_no',
        ]);

        $remote      = null;
        $remoteError = '';

        if ($this->transformService->canManageService($service)) {
            try {
                $remote = $this->fetchRemoteState($service);
                if (! empty($remote['host']) || ! empty($remote['runtime']) || ! empty($remote['nat'])) {
                    $this->syncServiceFromRemote($service, $remote['host'] ?? [], $remote['runtime'] ?? [], $remote['nat'] ?? []);
                    $service->refresh()->loadMissing([
                        'product:id,name,product_type,supplier_id,provision_module,config_options,pricing',
                        'product.categoryMapping:id,legacy_group_id,parent_id,product_type,name,title,slogan,slug',
                        'product.categoryMapping.parent:id,legacy_group_id,parent_id,product_type,name,title,slogan,slug',
                        'product.supplier',
                        'order:id,order_no,status,paid_at,created_at',
                        'order.invoice:id,order_id,invoice_no',
                    ]);
                }
            } catch (\Throwable $exception) {
                Log::warning('[服务详情] 远程状态补丁刷新失败', [
                    'service_id' => (int) $service->id,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
                $remoteError = $exception->getMessage();
            }
        }

        $detail = $this->transformService->transformDetail($service, $remote, $remoteError);

        return [
            'id'           => (int) ($detail['id'] ?? 0),
            'name'         => (string) ($detail['name'] ?? ''),
            'domain'       => (string) ($detail['domain'] ?? ''),
            'status'       => (int) ($detail['status'] ?? 0),
            'status_label' => (string) ($detail['status_label'] ?? ''),
            'status_tone'  => (string) ($detail['status_tone'] ?? 'info'),
            'expires_at'   => (string) ($detail['expires_at'] ?? ''),
            'created_at'   => (string) ($detail['created_at'] ?? ''),
            'upstream'     => is_array($detail['upstream'] ?? null) ? $detail['upstream'] : [],
            'runtime'      => is_array($detail['runtime'] ?? null) ? $detail['runtime'] : [],
            'connection'   => is_array($detail['connection'] ?? null) ? $detail['connection'] : [],
            'specs'        => is_array($detail['specs'] ?? null) ? $detail['specs'] : [],
            'actions'      => is_array($detail['actions'] ?? null) ? $detail['actions'] : [],
        ];
    }

    public function getOperationLogsForUser(User $user, int $serviceId, array $filters = [], int $perPage = 10): array
    {
        $service = $this->findUserService($user, $serviceId);
        $query   = \App\Models\OperationLog::query()
            ->where(function (Builder $builder) use ($serviceId) {
                $builder->where(function (Builder $serviceBuilder) use ($serviceId) {
                    $serviceBuilder->where('module', 'service')->where('subject_id', $serviceId);
                })->orWhere('context->service_id', $serviceId);
            });

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword) {
                $builder->where('action', 'like', '%' . $keyword . '%')
                    ->orWhere('context->summary', 'like', '%' . $keyword . '%')
                    ->orWhere('context->actor_name', 'like', '%' . $keyword . '%')
                    ->orWhere('context->operator_name', 'like', '%' . $keyword . '%')
                    ->orWhere('context->service_name', 'like', '%' . $keyword . '%')
                    ->orWhere('context->group_name', 'like', '%' . $keyword . '%')
                    ->orWhere('context->forwarding_name', 'like', '%' . $keyword . '%');
            });
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $query->where(function (Builder $builder) use ($category) {
                $builder->where('context->category', $category);
                if ($category === 'service') {
                    $builder->orWhere('action', 'order.service.manual_create');
                }
            });
        }

        $latestLog = (clone $query)->orderByDesc('id')->first(['id', 'created_at']);
        $paginator = $query->orderByDesc('id')->paginate($perPage);

        return [
            'list'      => collect($paginator->items())
                ->map(fn (OperationLog $log) => $this->transformService->transformServiceOperationLog($log))
                ->values()->all(),
            'summary'   => [
                'total'          => $paginator->total(),
                'today_total'    => (clone $query)->where('created_at', '>=', now()->startOfDay())->count(),
                'latest_created_at' => $latestLog?->created_at?->format('Y-m-d H:i:s'),
                'service_name'   => (string) $service->name,
            ],
            'total'     => $paginator->total(),
            'page'      => $paginator->currentPage(),
            'page_size' => $paginator->perPage(),
        ];
    }

    public function updateRemarkForUser(User $user, int $serviceId, ?string $remark, array $context = []): array
    {
        $service = $this->findUserService($user, $serviceId, [
            'product:id,name,product_type,category_id,config_options',
            'product.categoryMapping:id,legacy_group_id,parent_id,product_type,name,title,slogan,slug',
            'product.categoryMapping.parent:id,legacy_group_id,parent_id,product_type,name,title,slogan,slug',
            'order:id,order_no,status,paid_at',
        ]);

        $cleanRemark   = TextSanitizer::clean($remark);
        $provisionData = (array) ($service->provision_data ?? []);
        $provisionData['client_remark'] = $cleanRemark;

        $service->forceFill(['provision_data' => $provisionData])->save();

        $this->operationLogService->writeServiceConsoleLog($service, 'service.console.remark.update', [
            'category' => 'service',
            'summary'  => $cleanRemark !== '' ? '更新服务备注' : '清空服务备注',
            'remark'   => $cleanRemark,
        ], $context);

        return $this->transformService->transformListItem($service);
    }

    // ── Remote sync (shared with power/vnc sub-services via this class) ───

    public function syncServiceFromRemote(Service $service, array $host, array $runtime = [], array $nat = []): void
    {
        if ($host === [] && $runtime === [] && $nat === []) {
            return;
        }

        $currentProvisionData = (array) ($service->provision_data ?? []);
        $cachedConnection     = $this->transformService->readCachedConnection($currentProvisionData);
        $natRemote            = $this->transformService->resolveNatRemoteSnapshot($currentProvisionData, $nat);
        $normalizedHostStatus = strtolower(trim((string) ($host['domainstatus'] ?? '')));
        $shouldResetRuntimeSnapshot = $host !== []
            && $normalizedHostStatus !== ''
            && $normalizedHostStatus !== 'active';

        $mergedConnection = [
            'hostname'    => ServiceHostname::resolveConnectionHostname($service, $currentProvisionData, $cachedConnection, $host),
            'username'    => trim((string) ($host['username'] ?? ($cachedConnection['username'] ?? ''))),
            'password'    => $this->resolveConnectionPasswordFromHost($host, $cachedConnection),
            'port'        => (int) (($host['port'] ?? ($cachedConnection['port'] ?? 0)) ?: 0),
            'internal_ip' => trim((string) ($host['internalip'] ?? $host['privateip'] ?? ($cachedConnection['internal_ip'] ?? ''))),
        ];

        $provisionData = array_merge($currentProvisionData, [
            'upstream_status'        => (string) ($host['domainstatus'] ?? ($currentProvisionData['upstream_status'] ?? '')),
            'upstream_product_id'    => (int) (($host['product_id'] ?? ($currentProvisionData['upstream_product_id'] ?? 0)) ?: 0),
            'upstream_product_name'  => trim((string) ($host['product_name'] ?? ($currentProvisionData['upstream_product_name'] ?? ''))),
            'dedicated_ip'           => (string) ($host['dedicatedip'] ?? ($currentProvisionData['dedicated_ip'] ?? '')),
            'assigned_ips'           => is_array($host['assignedips'] ?? null) ? $host['assignedips'] : (array) ($currentProvisionData['assigned_ips'] ?? []),
            'host_config_option'     => is_array($host['config_option'] ?? null) ? $host['config_option'] : (array) ($currentProvisionData['host_config_option'] ?? []),
            'os'                     => (string) ($host['os'] ?? ($currentProvisionData['os'] ?? '')),
            'runtime_status'         => array_key_exists('status', $runtime)
                ? (string) ($runtime['status'] ?? '')
                : ($shouldResetRuntimeSnapshot ? '' : (string) ($currentProvisionData['runtime_status'] ?? '')),
            'runtime_description'    => array_key_exists('des', $runtime)
                ? (string) ($runtime['des'] ?? '')
                : ($shouldResetRuntimeSnapshot ? '' : (string) ($currentProvisionData['runtime_description'] ?? '')),
            'nat_remote_address'     => $natRemote['remote_address'],
            'nat_remote_host'        => $natRemote['host'],
            'nat_remote_port'        => $natRemote['port'],
            'nat_remote_checked_at'  => $natRemote['checked_at'],
            'connection_secret'      => $this->transformService->writeCachedConnection($mergedConnection),
            'connection_cached_at'   => now()->format('Y-m-d H:i:s'),
            'last_synced_at'         => now()->format('Y-m-d H:i:s'),
        ]);

        $service->forceFill([
            'name'             => (string) ($host['product_name'] ?? $service->name),
            'domain'           => trim((string) ($host['domain'] ?? $service->domain)),
            'status'           => $host !== []
                ? $this->transformService->resolveServiceStatusFromUpstream((string) ($host['domainstatus'] ?? ''))
                : $service->status,
            'expires_at'       => $host !== []
                ? $this->transformService->resolveExpiry($host, $service)
                : $service->expires_at,
            'suspended_reason' => null,
            'provision_data'   => $provisionData,
        ])->save();
    }

    public function fetchRemoteState(Service $service, ?Supplier $supplier = null, ?string $jwt = null): array
    {
        if (! $supplier || ! $jwt) {
            [$supplier, $hostId, $jwt] = $this->resolveUpstreamContext($service);
        } else {
            $provisionData = (array) ($service->provision_data ?? []);
            $hostId        = (int) (($provisionData['upstream_host_id'] ?? 0) ?: 0);
        }

        $detailResponse = $this->mofangFinanceClient->get($supplier, "/v1/hosts/{$hostId}", $jwt);
        $this->assertSuccess($detailResponse, '读取主机详情');

        $detailPayload = $this->extractPayload($detailResponse);
        $statusPayload = [];

        try {
            $statusPayload = $this->fetchModuleStatusPayload($supplier, $hostId, $jwt, 'host');
        } catch (\Throwable $exception) {
            Log::warning('[实例详情] 上游 runtime 读取失败', [
                'service_id' => $service->id,
                'host_id'    => $hostId,
                'message'    => $exception->getMessage(),
            ]);
        }

        $natPayload = [];

        try {
            $natPayload = $this->fetchNatRemoteConnection($supplier, $hostId, $jwt);
        } catch (\Throwable $exception) {
            Log::info('[实例详情] NAT远程地址获取失败', [
                'service_id' => $service->id,
                'host_id'    => $hostId,
                'message'    => $exception->getMessage(),
            ]);
        }

        return [
            'host'    => is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [],
            'runtime' => is_array($statusPayload) ? $statusPayload : [],
            'nat'     => $natPayload,
        ];
    }

    public function fetchSupportedModules(Supplier $supplier, int $hostId, string $jwt): array
    {
        $cacheKey      = $this->buildMonitorModuleCacheKey($supplier, $hostId);
        $cachedModules = Cache::get($cacheKey);

        if (is_array($cachedModules)) {
            return $cachedModules;
        }

        $response = $this->mofangFinanceClient->get($supplier, "/v1/hosts/{$hostId}/module", $jwt);
        $this->assertSuccess($response, '读取监控模块');

        $payload = $this->extractPayload($response);

        if ($payload === []) {
            Cache::put($cacheKey, [], now()->addSeconds(self::MONITOR_MODULE_CACHE_TTL_SECONDS));

            return [];
        }

        if (array_is_list($payload)) {
            $modules = array_values(array_filter($payload, 'is_array'));
            Cache::put($cacheKey, $modules, now()->addSeconds(self::MONITOR_MODULE_CACHE_TTL_SECONDS));

            return $modules;
        }

        foreach (['list', 'module', 'data'] as $key) {
            if (is_array($payload[$key] ?? null)) {
                $modules = array_values(array_filter($payload[$key], 'is_array'));
                Cache::put($cacheKey, $modules, now()->addSeconds(self::MONITOR_MODULE_CACHE_TTL_SECONDS));

                return $modules;
            }
        }

        Cache::put($cacheKey, [], now()->addSeconds(self::MONITOR_MODULE_CACHE_TTL_SECONDS));

        return [];
    }

    // ── Upstream context helpers ───────────────────────────────────────────

    public function resolveUpstreamContext(Service $service): array
    {
        throw_if(! $this->transformService->canManageService($service), new BusinessException('当前服务未接入可控的上游主机', 42200));

        [$supplier, $hostId] = $this->resolveManagedSupplierAndHost($service);
        $jwt = $this->mofangFinanceClient->login($supplier);

        return [$supplier, $hostId, $jwt];
    }

    public function resolveManagedSupplierAndHost(Service $service): array
    {
        $service->loadMissing('product.supplier');
        $supplier = $service->product?->supplier;

        throw_if(! $supplier instanceof Supplier, new BusinessException('供应商配置不存在', 42200));

        $provisionData = (array) ($service->provision_data ?? []);
        $hostId        = (int) (($provisionData['upstream_host_id'] ?? 0) ?: 0);
        throw_if($hostId <= 0, new BusinessException('当前服务未绑定有效的上游主机', 42200));

        return [$supplier, $hostId];
    }

    public function findUserService(User $user, int $serviceId, array $relations = []): Service
    {
        $service = Service::query()
            ->with($relations)
            ->where('user_id', $user->id)
            ->find($serviceId);

        throw_if(! $service, new BusinessException('服务不存在', 40400, 404));

        return $service;
    }

    // ── Module status helpers ──────────────────────────────────────────────

    public function assertSuccess(array $response, string $action): void
    {
        $status = (int) ($response['status'] ?? $response['code'] ?? 0);
        if (in_array($status, [200, 1001], true)) {
            return;
        }

        $message = trim((string) ($response['msg'] ?? $response['message'] ?? ''));
        throw new BusinessException($message !== '' ? "{$action}失败：{$message}" : "{$action}失败", 42200);
    }

    public function extractPayload(array $response): array
    {
        return is_array($response['data'] ?? null) ? $response['data'] : $response;
    }

    public function extractSecondVerify(array $response): array
    {
        $payload = $this->extractPayload($response);

        return is_array($payload['second_verify'] ?? null) ? $payload['second_verify'] : [];
    }

    public function fetchModuleStatusPayload(Supplier $supplier, int $hostId, string $jwt, string $type): array
    {
        $normalizedType = $this->normalizeModuleStatusType($type);
        $response       = $this->mofangFinanceClient->get($supplier, "/v1/hosts/{$hostId}/module/status", $jwt, [
            'type' => $normalizedType,
        ]);
        $this->assertSuccess($response, \App\Services\ClientServiceConsoleService::MODULE_STATUS_TYPES[$normalizedType] ?? '读取状态');

        return $this->extractPayload($response);
    }

    public function normalizeModuleStatusType(string $type): string
    {
        $normalizedType = strtolower(trim($type));
        throw_if(! isset(\App\Services\ClientServiceConsoleService::MODULE_STATUS_TYPES[$normalizedType]), new BusinessException('不支持的状态类型', 42200));

        return $normalizedType;
    }

    public function normalizeModuleStatus(array $payload, string $type): array
    {
        $status      = trim((string) ($payload['status'] ?? $payload['state'] ?? $payload['result'] ?? ''));
        $description = trim((string) ($payload['des'] ?? $payload['description'] ?? $payload['msg'] ?? $payload['message'] ?? ''));
        $progress    = $this->extractModuleProgress($payload, $status, $description);
        $isFailed    = $this->moduleStatusContainsKeyword($status, $description, [
            'fail', 'failed', 'error', 'cancel', '取消', '失败', '错误', '异常',
        ]);
        $isSuccess   = $type === 'host'
            ? ($status !== '' || $description !== '')
            : (
                $progress === 100
                || $this->moduleStatusContainsKeyword($status, $description, [
                    'success', 'successful', 'done', 'finish', 'finished', 'complete', 'completed', '成功', '完成', '已完成',
                ])
            );
        $isFinished  = $type === 'host'
            ? ($status !== '' || $description !== '')
            : ($isSuccess || $isFailed);

        return [
            'type'        => $type,
            'type_label'  => \App\Services\ClientServiceConsoleService::MODULE_STATUS_TYPES[$type] ?? '状态',
            'status'      => $status,
            'description' => $description,
            'progress'    => $progress,
            'is_finished' => $isFinished,
            'is_success'  => $isSuccess,
            'is_failed'   => $isFailed,
            'raw'         => $payload,
        ];
    }

    public function buildPasswordResetPendingStatus(): array
    {
        return [
            'type'        => 'repassword',
            'type_label'  => '密码重置状态',
            'status'      => 'unsupported',
            'description' => '当前上游不提供重置密码进度，请稍后使用新密码登录验证结果。',
            'progress'    => null,
            'is_finished' => true,
            'is_success'  => true,
            'is_failed'   => false,
            'raw'         => ['unsupported' => true],
        ];
    }

    public function buildReinstallOptionsCacheKey(int $supplierId, int $hostId): string
    {
        return "mofang_finance:reinstall_options:{$supplierId}:{$hostId}";
    }

    // ── Cache key helpers ──────────────────────────────────────────────────

    public function buildDetailResponseCacheKey(Service $service): string
    {
        return 'service_console:detail:' . $service->id . ':' . $service->user_id . ':' . optional($service->updated_at)?->timestamp;
    }

    public function buildServiceConfigCacheKey(Service $service): string
    {
        return 'service_console:config:' . $service->id . ':' . $service->user_id . ':' . optional($service->updated_at)?->timestamp;
    }

    public function buildMonitorModuleCacheKey(Supplier $supplier, int $hostId): string
    {
        return "mofang_finance:host_modules:{$supplier->id}:{$hostId}";
    }

    public function forgetDetailCaches(Service $service): void
    {
        Cache::forget($this->buildDetailResponseCacheKey($service));
        Cache::forget($this->buildServiceConfigCacheKey($service));
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function needsRemoteSnapshotRefresh(Service $service): bool
    {
        $provisionData = (array) ($service->provision_data ?? []);
        $lastSyncedAt  = trim((string) ($provisionData['last_synced_at'] ?? ''));

        if ($lastSyncedAt === '') {
            return true;
        }

        try {
            return Carbon::parse($lastSyncedAt)
                ->addSeconds(self::DETAIL_REMOTE_SNAPSHOT_TTL_SECONDS)
                ->isPast();
        } catch (\Throwable) {
            return true;
        }
    }

    private function needsConnectionHydration(Service $service): bool
    {
        $provisionData          = (array) ($service->provision_data ?? []);
        $hasRemoteSnapshot      = trim((string) ($provisionData['last_synced_at'] ?? '')) !== ''
            || trim((string) ($provisionData['connection_cached_at'] ?? '')) !== '';
        $hasConnectionCache     = trim((string) ($provisionData['connection_cached_at'] ?? '')) !== '';
        $hasHostConfigOption    = is_array($provisionData['host_config_option'] ?? null)
            && (array) ($provisionData['host_config_option'] ?? []) !== [];
        $hasUpstreamProductId   = (int) (($provisionData['upstream_product_id'] ?? 0) ?: 0) > 0;

        if ($hasRemoteSnapshot) {
            return false;
        }

        if ($hasConnectionCache && $hasHostConfigOption && $hasUpstreamProductId) {
            return false;
        }

        $cachedConnection = $this->transformService->readCachedConnection($provisionData);

        return ! $hasHostConfigOption
            || ! $hasUpstreamProductId
            || trim((string) ($cachedConnection['username'] ?? '')) === ''
            || trim((string) ($cachedConnection['password'] ?? '')) === '';
    }

    private function needsRuntimeStatusRefresh(Service $service): bool
    {
        $provisionData      = (array) ($service->provision_data ?? []);
        $runtimeStatus      = strtolower(trim((string) ($provisionData['runtime_status'] ?? '')));
        $runtimeDescription = trim((string) ($provisionData['runtime_description'] ?? ''));

        if ($runtimeStatus === '' && $runtimeDescription === '') {
            return false;
        }

        if (in_array($runtimeStatus, ['process', 'task', 'starting', 'booting', 'stopping', 'shutting_down', 'reboot', 'rebooting'], true)) {
            return true;
        }

        return preg_match('/处理中|开机中|关机中|重启中/u', $runtimeDescription) === 1;
    }

    private function needsNatRemoteHydration(Service $service): bool
    {
        $provisionData = (array) ($service->provision_data ?? []);

        return trim((string) ($provisionData['nat_remote_checked_at'] ?? '')) === '';
    }

    private function fetchNatRemoteConnection(Supplier $supplier, int $hostId, string $jwt): array
    {
        $url  = $this->resolveNatRemoteDetailRootUrl($supplier) . '/servicedetail?' . http_build_query([
            'action' => 'nat',
            'id'     => $hostId,
        ]);
        $html = $this->mofangFinanceClient->getText($supplier, $url, $jwt);

        return $this->parseNatServiceDetailPage($html);
    }

    private function parseNatServiceDetailPage(string $html): array
    {
        $html          = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $remoteAddress = $this->extractNatRemoteAddress($html);
        [$host, $port] = $this->splitNatAclExternalAddress($remoteAddress);

        return [
            'checked'        => true,
            'checked_at'     => now()->format('Y-m-d H:i:s'),
            'remote_address' => $remoteAddress,
            'host'           => $host,
            'port'           => (int) ($port !== '' ? $port : 0),
        ];
    }

    private function extractNatRemoteAddress(string $html): string
    {
        $xpath = $this->createHtmlXPath($html);

        if ($xpath) {
            $nodes = $xpath->query("//*[@id='nat_aclBox']");
            if ($nodes && $nodes->length > 0) {
                return $this->normalizeNatRemoteAddressCandidate($this->normalizeHtmlNodeText($nodes->item(0)));
            }

            $labelNodes = $xpath->query("//label[contains(normalize-space(.), '远程地址')]");
            if ($labelNodes && $labelNodes->length > 0) {
                foreach ($labelNodes as $labelNode) {
                    $candidate = $this->extractNatRemoteAddressFromLabelNode($xpath, $labelNode);
                    if ($candidate !== '') {
                        return $candidate;
                    }
                }
            }
        }

        if (preg_match('/id\s*=\s*[\'"]nat_aclBox[\'"][^>]*>([^<]+)/iu', $html, $matches) === 1) {
            return $this->normalizeNatRemoteAddressCandidate((string) ($matches[1] ?? ''));
        }

        $patterns = [
            '/远程地址\s*[：:]\s*<\/label>\s*<span[^>]*>([^<]+)/iu',
            '/远程地址\s*[：:]\s*<\/[^>]+>\s*<span[^>]*>([^<]+)/iu',
            '/远程地址\s*[：:]\s*([^<]+)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                $candidate = $this->normalizeNatRemoteAddressCandidate((string) ($matches[1] ?? ''));
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private function splitNatAclExternalAddress(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return ['', ''];
        }

        if (preg_match('/^(.*):(\d{1,5})$/', $value, $matches) === 1) {
            return [trim((string) ($matches[1] ?? '')), trim((string) ($matches[2] ?? ''))];
        }

        return [$value, ''];
    }

    private function resolveSupplierRootUrl(Supplier $supplier): string
    {
        $baseUrl = trim((string) $supplier->api_url);
        $parts   = parse_url($baseUrl);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return rtrim($baseUrl, '/');
        }

        $rootUrl = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $rootUrl .= ':' . $parts['port'];
        }

        return $rootUrl;
    }

    private function resolveNatRemoteDetailRootUrl(Supplier $supplier): string
    {
        $rootUrl = $this->resolveSupplierRootUrl($supplier);
        $parts   = parse_url($rootUrl);
        $host    = strtolower(trim((string) ($parts['host'] ?? '')));

        if ($host !== '' && str_ends_with($host, 'meidecloud.com')) {
            return 'https://www.meidecloud.com';
        }

        return $rootUrl;
    }

    private function createHtmlXPath(string $html): ?\DOMXPath
    {
        if (trim($html) === '') {
            return null;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();

        if (! $loaded) {
            return null;
        }

        return new \DOMXPath($dom);
    }

    private function normalizeHtmlNodeText(\DOMNode $node): string
    {
        return trim(html_entity_decode($node->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function extractNatRemoteAddressFromLabelNode(\DOMXPath $xpath, \DOMNode $labelNode): string
    {
        $siblingNodes = $xpath->query('./following-sibling::*|./following-sibling::text()', $labelNode);

        if ($siblingNodes) {
            foreach ($siblingNodes as $siblingNode) {
                $text = $siblingNode instanceof \DOMText
                    ? trim((string) $siblingNode->nodeValue)
                    : $this->normalizeHtmlNodeText($siblingNode);
                $candidate = $this->normalizeNatRemoteAddressCandidate($text);

                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        $parentNode = $labelNode->parentNode;
        if ($parentNode instanceof \DOMNode) {
            return $this->normalizeNatRemoteAddressCandidate($this->normalizeHtmlNodeText($parentNode));
        }

        return '';
    }

    private function normalizeNatRemoteAddressCandidate(string $value): string
    {
        $value = html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = str_replace(['远程地址：', '远程地址:'], '', $value);
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/((?:\d{1,3}\.){3}\d{1,3}(?::\d{1,5})?|(?:[a-z0-9-]+\.)+[a-z0-9-]+(?::\d{1,5})?)/iu', $value, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    private function resolveConnectionPasswordFromHost(array $host, array $cachedConnection): string
    {
        $remotePassword = trim((string) ($host['password'] ?? ''));
        if ($remotePassword === '') {
            return trim((string) ($cachedConnection['password'] ?? ''));
        }

        $isMasked = preg_match('/^[*]+$/', $remotePassword) === 1
            || in_array(mb_strtolower($remotePassword), ['hidden', 'masked', 'secret'], true);

        return $isMasked
            ? trim((string) ($cachedConnection['password'] ?? ''))
            : $remotePassword;
    }

    private function extractModuleProgress(array $payload, string $status = '', string $description = ''): ?int
    {
        foreach (['progress', 'percent', 'percentage'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_numeric($value)) {
                return max(0, min(100, (int) round((float) $value)));
            }
        }

        $combined = trim($status . ' ' . $description);
        if ($combined !== '' && preg_match('/(\d{1,3})\s*%/', $combined, $matches) === 1) {
            return max(0, min(100, (int) $matches[1]));
        }

        return null;
    }

    private function moduleStatusContainsKeyword(string $status, string $description, array $keywords): bool
    {
        $haystack = strtolower(trim($status . ' ' . $description));
        if ($haystack === '') {
            return false;
        }

        foreach ($keywords as $keyword) {
            if (str_contains($haystack, strtolower((string) $keyword))) {
                return true;
            }
        }

        return false;
    }
}
