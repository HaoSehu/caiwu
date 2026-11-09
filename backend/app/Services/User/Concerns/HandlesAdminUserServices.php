<?php

declare(strict_types=1);

namespace App\Services\User\Concerns;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Jobs\SendTemplateEmailJob;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceOverviewService;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\Integrations\Plugins\ServiceUpstreamBindingWriter;
use App\Services\Integrations\Plugins\UpstreamBindingWriter;
use App\Services\Notification\UserNotificationService;
use App\Services\System\NotificationService;
use App\Services\Upstream\Contracts\ProvidesConsoleRuntime;
use App\Services\Upstream\ProviderResolver;
use App\Support\BillingCycle;
use App\Support\Money;
use App\Support\ServiceHostname;
use App\Support\TextSanitizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait HandlesAdminUserServices
{
    /**
     * 请求内复用的绑定解析器实例（trait 属性，宿主类未定义同名属性）。
     */
    private ?PluginBindingResolver $bindingResolver = null;

    /**
     * 用户服务列表
     */
    public function services(User $user, array $filters, int $perPage = 20)
    {
        return $this->clientServiceConsoleService->paginateForUser($user, array_merge($filters, [
            'page_size' => $perPage,
        ]));
    }

    /**
     * 用户服务详情
     */
    public function serviceDetail(
        User $user,
        int $serviceId,
        bool $refreshRemote = false,
        bool $includeSensitiveConnection = true,
        bool $syncRemote = true
    ): array {
        return $this->clientServiceConsoleService->getDetailForUser(
            $user,
            $serviceId,
            $refreshRemote,
            $includeSensitiveConnection,
            $syncRemote
        );
    }

    public function serviceBaseDetail(User $user, int $serviceId, bool $includeSensitiveConnection = true): array
    {
        return $this->clientServiceConsoleService->getBaseDetailForUser($user, $serviceId, $includeSensitiveConnection);
    }

    public function serviceRemoteStatusPatch(User $user, int $serviceId, bool $includeSensitiveConnection = true): array
    {
        return $this->clientServiceConsoleService->getRemoteStatusPatchForUser($user, $serviceId, $includeSensitiveConnection);
    }

    /**
     * 服务实例操作日志（管理端口径：包含管理员操作）
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function serviceOperationLogs(User $user, int $serviceId, array $filters, int $perPage = 20): array
    {
        return $this->clientServiceConsoleService->getOperationLogsForUser($user, $serviceId, $filters, $perPage, true);
    }

    public function refreshServiceStatuses(User $user, array $serviceIds = []): array
    {
        $ids = collect($serviceIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $services = Service::query()
            ->select([
                'id',
                'user_id',
                'product_id',
                'name',
                'domain',
                'status',
                'provision_data',
                'expires_at',
            ])
            ->where('user_id', $user->id)
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->get();

        throw_if($services->isEmpty(), new BusinessException('没有可刷新的服务记录'));

        return $this->serviceStatusSyncService->syncServices($services);
    }

    /**
     * 更新用户服务业务信息
     */
    public function updateServiceMeta(User $user, int $serviceId, array $data, array $context = []): array
    {
        $service = Service::query()
            ->with(['product.supplier', 'user'])
            ->where('user_id', $user->id)
            ->findOrFail($serviceId);

        $currentProvisionData = $this->bindingResolver()->serviceProvisionData($service);
        $previousCustomHostname = ServiceHostname::custom($currentProvisionData);
        $previousAmount = round((float) ($service->amount ?? 0), 2);
        $previousSupplierId = $this->resolveServiceSupplierId($service, $currentProvisionData);
        $previousUpstreamProductId = $this->resolveServiceUpstreamProductId($service, $currentProvisionData);
        $previousUpstreamHostId = $this->resolveServiceUpstreamHostId($service, $currentProvisionData);
        $supportsUpstream = $this->supportsManagedUpstream($service->product);
        $hasUpstreamBinding = $previousSupplierId > 0 || $this->bindingResolver()->upstreamServiceIdForService($service) !== null;
        $rawAmount = $data['amount'] ?? null;
        $newAmount = $rawAmount === null ? null : round((float) $rawAmount, 2);
        $rawSupplierId = $data['supplier_id'] ?? null;
        $supplierId = $rawSupplierId === null ? null : (int) $rawSupplierId;
        $rawUpstreamProductId = $data['upstream_product_id'] ?? null;
        $upstreamProductId = $rawUpstreamProductId === null ? null : (int) $rawUpstreamProductId;
        $rawUpstreamHostId = $data['upstream_host_id'] ?? null;
        $upstreamHostId = $rawUpstreamHostId === null ? null : (int) $rawUpstreamHostId;
        $selectedSupplier = null;

        if ($newAmount !== null) {
            throw_if($newAmount < 0, new BusinessException('请输入有效的购买价格'));
        }

        if ($supplierId !== null) {
            $selectedSupplier = Supplier::query()
                ->enabled()
                ->find($supplierId);

            throw_if(! $selectedSupplier instanceof Supplier, new BusinessException('请选择有效的上游接口'));
            app(UpstreamBindingWriter::class)->syncSupplierBinding($selectedSupplier);
            throw_if(
                ! app(ProviderResolver::class)->resolveForSupplier($selectedSupplier)->supports(ProvidesConsoleRuntime::class),
                new BusinessException('当前上游接口不支持实例控制')
            );
            throw_if(
                $upstreamHostId === null && $upstreamProductId === null,
                new BusinessException('重新绑定上游接口时必须填写新的上游产品 ID 或上游实例 ID')
            );
        }

        if ($upstreamProductId !== null) {
            throw_if($upstreamProductId <= 0, new BusinessException('请输入有效的上游产品 ID'));
            throw_if(
                $supplierId === null && $upstreamHostId === null,
                new BusinessException('更换上游产品 ID 时必须同时绑定上游接口或上游实例 ID')
            );
        }

        if ($upstreamHostId !== null) {
            throw_if($upstreamHostId <= 0, new BusinessException('请输入有效的上游主机 ID'));
            throw_if(
                ! $supportsUpstream && ! $hasUpstreamBinding && ! $selectedSupplier instanceof Supplier,
                new BusinessException('当前服务未接入可控上游，无法修改上游主机 ID')
            );
        }

        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $operatorId = (int) (($context['operator_id'] ?? 0) ?: 0);
        $operatorName = trim((string) ($context['operator_name'] ?? ''));
        $rawServiceName = array_key_exists('service_name', $data) ? (string) ($data['service_name'] ?? '') : null;
        $newServiceName = $rawServiceName === null ? 'skip' : mb_substr(TextSanitizer::clean($rawServiceName), 0, 120);
        $clearCustomHostname = ! empty($data['clear_custom_hostname']);
        $newCustomHostname = 'skip';

        if ($clearCustomHostname) {
            $newCustomHostname = '';
        } elseif (array_key_exists('custom_hostname', $data)) {
            $rawCustomHostname = trim((string) ($data['custom_hostname'] ?? ''));
            $normalizedCustomHostname = $rawCustomHostname !== ''
                ? $this->settingService->normalizeHostname($rawCustomHostname, true)
                : '';

            if ($rawCustomHostname !== '' && $normalizedCustomHostname === '') {
                throw new BusinessException('请输入有效的自定义主机名');
            }

            $newCustomHostname = $normalizedCustomHostname;
        }

        $productPricing = Service::extractSupportedRenewPricing(
            is_array($service->product?->pricing ?? null) ? $service->product->pricing : []
        );
        $currentRenewPricing = $service->resolveRenewPricingConfig($productPricing);

        // 处理续费配置：clear_locked_pricing=true 恢复默认快照，否则按传入值更新，未传则跳过
        $clearLocked = ! empty($data['clear_locked_pricing']);
        $newLockedPricing = 'skip';

        if ($clearLocked) {
            $newLockedPricing = $service->resetRenewPricingConfig($productPricing);
        } elseif (isset($data['locked_pricing']) && is_array($data['locked_pricing'])) {
            $newLockedPricing = [];

            foreach (Service::SUPPORTED_RENEW_BILLING_CYCLES as $cycle => $cycleLabel) {
                $incoming = is_array($data['locked_pricing'][$cycle] ?? null) ? $data['locked_pricing'][$cycle] : [];
                $currentCycleConfig = is_array($currentRenewPricing[$cycle] ?? null) ? $currentRenewPricing[$cycle] : [
                    'enabled' => false,
                    'base_amount' => null,
                    'manual_amount' => null,
                ];

                $baseAmount = $this->normalizePositiveAmount($currentCycleConfig['base_amount'] ?? null);
                $manualAmount = array_key_exists('manual_amount', $incoming)
                    ? $this->normalizePositiveAmount($incoming['manual_amount'])
                    : $this->normalizePositiveAmount($currentCycleConfig['manual_amount'] ?? null);
                $enabled = array_key_exists('enabled', $incoming)
                    ? filter_var($incoming['enabled'], FILTER_VALIDATE_BOOLEAN)
                    : (bool) ($currentCycleConfig['enabled'] ?? false);

                $effectiveAmount = $manualAmount !== null && $manualAmount > 0 ? $manualAmount : $baseAmount;
                if ($enabled && ($effectiveAmount === null || $effectiveAmount <= 0)) {
                    throw new BusinessException("{$cycleLabel}已开启，请填写有效续费价格");
                }

                $newLockedPricing[$cycle] = [
                    'enabled' => (bool) ($enabled && $effectiveAmount !== null && $effectiveAmount > 0),
                    'base_amount' => $baseAmount !== null && $baseAmount > 0 ? number_format($baseAmount, 2, '.', '') : null,
                    'manual_amount' => $manualAmount !== null && $manualAmount > 0 ? number_format($manualAmount, 2, '.', '') : null,
                ];
            }
        }

        $supplierChanged = false;
        $supplierProductChanged = false;
        $amountChanged = false;
        $upstreamHostChanged = false;

        DB::transaction(function () use (
            $service,
            $newAmount,
            $selectedSupplier,
            $upstreamHostId,
            $upstreamProductId,
            $currentProvisionData,
            $traceId,
            $operatorId,
            $operatorName,
            $newLockedPricing,
            $newServiceName,
            $newCustomHostname,
            &$supplierChanged,
            &$supplierProductChanged,
            &$amountChanged,
            &$upstreamHostChanged,
            $previousAmount,
            $previousSupplierId,
            $previousUpstreamProductId,
            $previousUpstreamHostId
        ) {
            $provisionData = $currentProvisionData;
            $fillData = [];

            if ($newAmount !== null) {
                $fillData['amount'] = $newAmount;
                $amountChanged = abs($newAmount - $previousAmount) > 0.0001;
            }

            if ($selectedSupplier instanceof Supplier) {
                $providerKey = $this->resolveSupplierProviderKey($selectedSupplier);
                throw_if($providerKey === '', new BusinessException('供应商未配置上游插件绑定，无法绑定上游实例'));

                $provisionData['source_type'] = 'upstream';
                $provisionData['provider_key'] = $providerKey;
                $provisionData['supplier_id'] = (int) $selectedSupplier->id;
                $provisionData['upstream_product_id'] = $upstreamProductId ?? $this->resolveProductUpstreamProductId($service->product);
                $supplierChanged = (int) $selectedSupplier->id !== $previousSupplierId;
                $supplierProductChanged = (int) $provisionData['upstream_product_id'] !== $previousUpstreamProductId;
            }

            if ($upstreamHostId !== null) {
                $provisionData['source_type'] = 'upstream';
                $existingProvider = $selectedSupplier instanceof Supplier
                    ? $this->resolveSupplierProviderKey($selectedSupplier)
                    : $this->resolveServiceProviderKey($service, $provisionData);
                throw_if($existingProvider === '', new BusinessException('服务未配置上游插件绑定，无法绑定上游实例'));

                $provisionData['provider_key'] = $existingProvider;
                $provisionData['supplier_id'] = $selectedSupplier instanceof Supplier
                    ? (int) $selectedSupplier->id
                    : $this->resolveServiceSupplierId($service, $provisionData);
                $provisionData['upstream_product_id'] = $upstreamProductId
                    ?? $this->resolveServiceUpstreamProductId($service, $provisionData);
                $supplierProductChanged = (int) $provisionData['upstream_product_id'] !== $previousUpstreamProductId;
                $provisionData['upstream_host_id'] = $upstreamHostId;
                $provisionData['last_manual_linked_at'] = now()->format('Y-m-d H:i:s');
                $upstreamHostChanged = $upstreamHostId !== $previousUpstreamHostId;
            }

            if ($supplierChanged || $supplierProductChanged || $upstreamHostChanged) {
                unset(
                    $provisionData['connection_secret'],
                    $provisionData['connection_cached_at'],
                    $provisionData['last_synced_at'],
                    $provisionData['last_status_sync_at'],
                    $provisionData['last_status_sync_attempt_at'],
                    $provisionData['status_sync_error'],
                    $provisionData['runtime_status'],
                    $provisionData['runtime_description'],
                    $provisionData['host_config_option'],
                    $provisionData['assigned_ips'],
                    $provisionData['dedicated_ip'],
                    $provisionData['nat_remote_address'],
                    $provisionData['nat_remote_host'],
                    $provisionData['nat_remote_port'],
                    $provisionData['nat_remote_checked_at'],
                    $provisionData['upstream_status'],
                    $provisionData['upstream_product_name'],
                    $provisionData['os'],
                    $provisionData['provision_error']
                );
            }

            if ($traceId !== '') {
                $provisionData['trace_id'] = $traceId;
            }

            if ($operatorId > 0) {
                $provisionData['updated_from_admin_id'] = $operatorId;
            }

            if ($operatorName !== '') {
                $provisionData['updated_from_admin_name'] = $operatorName;
            }

            if ($newServiceName !== 'skip') {
                $provisionData = ServiceHostname::rememberDefaultServiceName($provisionData, (string) ($service->name ?? ''));
                $provisionData = ServiceHostname::writeCustomServiceName($provisionData, (string) $newServiceName, [
                    'operator_id' => $operatorId,
                    'operator_name' => $operatorName,
                ]);
                $fillData['name'] = ServiceHostname::resolveInstanceName($service, $provisionData);
            }

            if ($newCustomHostname !== 'skip') {
                $provisionData = ServiceHostname::writeCustomHostname($provisionData, (string) $newCustomHostname, [
                    'operator_id' => $operatorId,
                    'operator_name' => $operatorName,
                ]);
            }
            $fillData['provision_data'] = $provisionData;

            // 'skip' 表示请求未传 locked_pricing 字段，保持现有值不变
            if ($newLockedPricing !== 'skip') {
                $fillData['locked_pricing'] = $newLockedPricing;
            }

            $service->forceFill($fillData)->save();
        });

        $service = $service->refresh()->loadMissing(['product', 'order']);
        if ($supplierChanged || $supplierProductChanged || $upstreamHostChanged || $newServiceName !== 'skip' || $newCustomHostname !== 'skip') {
            app(ServiceUpstreamBindingWriter::class)->syncServiceState(
                $service,
                $service->product,
                (array) ($service->provision_data ?? [])
            );
        }
        app(ServiceDetailService::class)->forgetDetailCaches($service);
        // 服务名称/金额等概览字段可能已变化，事务提交后同步失效该用户概览缓存。
        app(ServiceOverviewService::class)->forgetGroupedOverviewCache((int) $user->id);

        if ($newCustomHostname !== 'skip' && $previousCustomHostname !== $newCustomHostname) {
            try {
                $this->operationLogService->writeServiceConsoleLog($service, 'service.console.hostname.update', [
                    'category' => 'service',
                    'summary' => $newCustomHostname !== '' ? '设置自定义主机名' : '清空自定义主机名',
                    'hostname' => $newCustomHostname,
                    'previous_hostname' => $previousCustomHostname,
                ], [
                    'actor_type' => 'admin',
                    'actor_user_id' => $operatorId,
                    'actor_name' => $operatorName,
                    'trace_id' => $traceId,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('[管理员更新服务业务信息] 自定义主机名日志写入失败', [
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'operator_id' => $operatorId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        }

        if ($newServiceName !== 'skip') {
            try {
                $this->operationLogService->writeServiceConsoleLog($service, 'service.console.name.update', [
                    'category' => 'service',
                    'summary' => trim((string) ($service->name ?? '')) !== '' ? '管理员更新实例名称' : '管理员清空实例名称',
                    'service_name' => (string) ($service->name ?? ''),
                ], [
                    'actor_type' => 'admin',
                    'actor_user_id' => $operatorId,
                    'actor_name' => $operatorName,
                    'trace_id' => $traceId,
                    'ip_address' => (string) ($context['ip_address'] ?? ''),
                ]);
            } catch (\Throwable $exception) {
                Log::warning('[管理员更新服务业务信息] 实例名称日志写入失败', [
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'operator_id' => $operatorId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        }

        if ($amountChanged || $supplierChanged || $supplierProductChanged || $upstreamHostChanged || $newLockedPricing !== 'skip') {
            try {
                $renewPricingChanges = [];
                if ($newLockedPricing !== 'skip') {
                    foreach (Service::SUPPORTED_RENEW_BILLING_CYCLES as $cycle => $label) {
                        $entry = is_array($newLockedPricing[$cycle] ?? null) ? $newLockedPricing[$cycle] : [];
                        $renewPricingChanges[] = [
                            'billing_cycle' => $cycle,
                            'billing_cycle_label' => $label,
                            'enabled' => (bool) ($entry['enabled'] ?? false),
                            'manual_amount' => $entry['manual_amount'] ?? null,
                        ];
                    }
                }

                $latestProvisionData = $this->bindingResolver()->serviceProvisionData($service);
                $this->operationLogService->writeServiceConsoleLog($service, 'service.console.meta.update', [
                    'category' => 'service',
                    'summary' => '管理员更新实例业务信息',
                    'previous_amount' => number_format($previousAmount, 2, '.', ''),
                    'amount' => number_format((float) $service->amount, 2, '.', ''),
                    'previous_supplier_id' => $previousSupplierId > 0 ? $previousSupplierId : null,
                    'supplier_id' => (int) (($latestProvisionData['supplier_id'] ?? 0) ?: 0),
                    'supplier_name' => $selectedSupplier?->name,
                    'previous_upstream_product_id' => $previousUpstreamProductId > 0 ? $previousUpstreamProductId : null,
                    'upstream_product_id' => (int) (($latestProvisionData['upstream_product_id'] ?? 0) ?: 0),
                    'previous_upstream_host_id' => $previousUpstreamHostId > 0 ? $previousUpstreamHostId : null,
                    'upstream_host_id' => (int) (($latestProvisionData['upstream_host_id'] ?? 0) ?: 0),
                    'clear_locked_pricing' => ! empty($data['clear_locked_pricing']),
                    'renew_pricing_changes' => $renewPricingChanges,
                ], [
                    'actor_type' => 'admin',
                    'actor_user_id' => $operatorId,
                    'actor_name' => $operatorName,
                    'trace_id' => $traceId,
                    'ip_address' => (string) ($context['ip_address'] ?? ''),
                ]);
            } catch (\Throwable $exception) {
                Log::warning('[管理员更新服务业务信息] 操作日志写入失败', [
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'operator_id' => $operatorId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return $this->clientServiceConsoleService->getDetailForUser($user, $serviceId, true);
    }

    /**
     * 用户服务电源操作
     */
    public function servicePower(User $user, int $serviceId, string $action, array $context = []): array
    {
        return $this->clientServiceConsoleService->powerActionForUser($user, $serviceId, $action, $context);
    }

    /**
     * 用户服务重置密码
     */
    public function serviceResetPassword(User $user, int $serviceId, array $data, array $context = []): array
    {
        return $this->clientServiceConsoleService->resetPasswordForUser($user, $serviceId, $data, $context);
    }

    /**
     * 管理员手动新增服务
     */
    public function createManualService(User $user, array $data, array $context = []): array
    {
        $product = Product::query()
            ->with('supplier')
            ->findOrFail((int) $data['product_id']);
        $sourceType = trim((string) ($data['source_type'] ?? 'manual'));

        throw_if((int) $product->status !== 1, new BusinessException('商品已下架，无法开通'));
        throw_if($sourceType === 'upstream' && (int) $product->stock === 0, new BusinessException('该商品库存不足，无法继续开通'));

        $billingCycle = trim((string) ($data['billing_cycle'] ?? ''));
        $cyclePrice = $product->getPriceByBillingCycle($billingCycle);

        throw_if(
            $cyclePrice <= 0,
            new BusinessException('所选计费周期不在当前商品的可售范围内')
        );

        $status = (int) ($data['status'] ?? ServiceStatus::ACTIVE);
        $domain = trim((string) ($data['domain'] ?? ''));
        $amount = isset($data['amount'])
            ? round((float) $data['amount'], 2)
            : round((float) $cyclePrice, 2);

        throw_if($amount < 0, new BusinessException('服务金额不能小于 0'));

        $supportsUpstream = $this->supportsManagedUpstream($product);
        $upstreamHostId = (int) (($data['upstream_host_id'] ?? 0) ?: 0);

        if ($sourceType === 'upstream') {
            throw_if(! $supportsUpstream, new BusinessException('当前商品未接入可控上游，无法绑定上游主机'));
            throw_if($upstreamHostId <= 0, new BusinessException('请输入有效的上游实例 ID'));
        }

        $createOrder = (int) ($data['create_order'] ?? 1) === 1;
        $createInvoice = (int) ($data['create_invoice'] ?? 1) === 1;
        $deductBalance = (int) ($data['deduct_balance'] ?? 1) === 1;

        throw_if($deductBalance && ! $createInvoice, new BusinessException('从余额扣款需同时开启自动创建账单'));

        $now = now();
        $expiresAt = $this->resolveManualServiceExpiresAt(
            $data['expires_at'] ?? null,
            $billingCycle,
            $status
        );
        $serviceName = $this->resolveManualServiceName($product, $data, $domain);
        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $operatorId = (int) (($context['operator_id'] ?? 0) ?: 0);
        $operatorName = trim((string) ($context['operator_name'] ?? ''));
        $ipAddress = trim((string) ($context['ip_address'] ?? ''));
        $autoRenew = (int) ($data['auto_renew'] ?? 1);
        $remark = trim((string) ($data['remark'] ?? ''));
        $upstreamStatus = trim((string) ($data['upstream_status'] ?? ''));
        $upstreamProviderKey = $sourceType === 'upstream'
            ? $this->resolveProductProviderKey($product)
            : '';
        throw_if($sourceType === 'upstream' && $upstreamProviderKey === '', new BusinessException('商品未配置上游插件绑定，无法创建上游服务'));

        $upstreamSupplierId = $sourceType === 'upstream' ? $this->resolveProductSupplierId($product) : 0;
        $upstreamProductId = $sourceType === 'upstream' ? $this->resolveProductUpstreamProductId($product) : 0;
        $dedicatedIp = trim((string) ($data['dedicated_ip'] ?? ''));
        $internalIp = trim((string) ($data['internal_ip'] ?? ''));
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $os = trim((string) ($data['os'] ?? ''));
        $port = (int) (($data['port'] ?? 0) ?: 0);

        $balanceTransactionId = 0;
        $balanceAfter = '';

        $service = DB::transaction(function () use (
            $user,
            $product,
            $billingCycle,
            $amount,
            $status,
            $sourceType,
            $domain,
            $now,
            $expiresAt,
            $serviceName,
            $traceId,
            $operatorId,
            $operatorName,
            $remark,
            $upstreamHostId,
            $upstreamStatus,
            $upstreamProviderKey,
            $upstreamSupplierId,
            $upstreamProductId,
            $dedicatedIp,
            $internalIp,
            $username,
            $password,
            $os,
            $port,
            $autoRenew,
            $createOrder,
            $createInvoice,
            $deductBalance,
            &$balanceTransactionId,
            &$balanceAfter
        ) {
            $order = null;
            if ($createOrder) {
                $order = Order::create([
                    'order_no' => Order::generateOrderNo(),
                    'projection_type' => Order::PROJECTION_TYPE_PROVISIONING,
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'product_spec_snapshot' => trim((string) $product->name),
                    'product_type_snapshot' => (string) $product->product_type,
                    'type' => 'new',
                    'amount' => $amount,
                    'discount' => 0,
                    'paid_amount' => 0,
                    'billing_cycle' => $billingCycle,
                    'config_snapshot' => array_filter([
                        'hostname' => $domain,
                        'source_type' => $sourceType,
                        'admin_manual' => true,
                        'remark' => $remark,
                        'trace_id' => $traceId,
                    ], fn ($value) => ! in_array($value, ['', null], true)),
                    'status' => OrderStatus::PENDING,
                    'operator' => $operatorName !== '' ? $operatorName : null,
                    'trace_id' => $traceId !== '' ? $traceId : null,
                ]);
            }

            $provisionData = array_filter([
                'source_type' => $sourceType,
                'provider_key' => $upstreamProviderKey,
                'supplier_id' => $upstreamSupplierId,
                'upstream_product_id' => $upstreamProductId,
                'upstream_host_id' => $sourceType === 'upstream' ? $upstreamHostId : 0,
                'upstream_status' => $sourceType === 'upstream'
                    ? ($upstreamStatus !== '' ? $upstreamStatus : ($status === ServiceStatus::ACTIVE ? 'active' : 'pending'))
                    : '',
                'dedicated_ip' => $dedicatedIp,
                'os' => $os,
                'requested_host' => $domain,
                'manual_remark' => $remark,
                'created_from_admin' => true,
                'created_from_admin_id' => $operatorId > 0 ? $operatorId : null,
                'created_from_admin_name' => $operatorName,
                'trace_id' => $traceId,
                'last_manual_linked_at' => $now->format('Y-m-d H:i:s'),
                'connection_secret' => $this->buildConnectionSecret([
                    'hostname' => $domain,
                    'username' => $username,
                    'password' => $password,
                    'port' => $port,
                    'internal_ip' => $internalIp,
                ]),
                'connection_cached_at' => ($domain !== '' || $username !== '' || $password !== '' || $port > 0 || $internalIp !== '')
                    ? $now->format('Y-m-d H:i:s')
                    : '',
            ], function ($value, string $key) {
                if (in_array($key, ['created_from_admin', 'source_type'], true)) {
                    return true;
                }

                return ! in_array($value, ['', null, 0], true);
            }, ARRAY_FILTER_USE_BOTH);

            $service = Service::create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'name' => $serviceName,
                'domain' => $domain,
                'billing_cycle' => $billingCycle,
                'amount' => $amount,
                'locked_pricing' => Service::buildDefaultRenewPricing(
                    is_array($product->pricing ?? null) ? $product->pricing : [],
                    $billingCycle,
                    $amount
                ),
                'status' => $status,
                'provision_data' => $provisionData,
                'expires_at' => $expiresAt,
                'auto_renew' => $autoRenew,
                'suspended_reason' => $status === ServiceStatus::PENDING
                    ? '管理员已创建服务，等待后续开通'
                    : ($status === ServiceStatus::SUSPENDED ? '管理员手动暂停' : null),
            ]);

            if ($order instanceof Order) {
                $order->forceFill([
                    'service_id' => $service->id,
                ])->save();
                $service->forceFill(['order_id' => $order->id])->save();
            }

            $invoice = null;
            if ($createInvoice) {
                $invoice = $order instanceof Order
                    ? $this->invoiceService->createFromOrder($order)
                    : $this->invoiceService->createDirect([
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'product_spec_snapshot' => trim((string) $product->name),
                        'product_type_snapshot' => (string) $product->product_type,
                        'service_id' => $service->id,
                        // 管理员手工开通实例直建账单：I6 值域收敛到 InvoiceType::MANUAL（历史写 'normal' 依赖 normalize 读兼容）
                        'type' => InvoiceType::MANUAL,
                        'amount' => $amount,
                        'billing_cycle' => $billingCycle,
                        'config_snapshot' => array_filter([
                            'hostname' => $domain,
                            'source_type' => $sourceType,
                            'admin_manual' => true,
                            'remark' => $remark,
                            'trace_id' => $traceId,
                        ], fn ($value) => ! in_array($value, ['', null], true)),
                        'trace_id' => $traceId !== '' ? $traceId : null,
                    ]);

                $invoice->forceFill([
                    'service_id' => $service->id,
                    'remark' => $remark !== '' ? $remark : null,
                    'operator' => $operatorName !== '' ? $operatorName : null,
                ])->save();

                $provisionData['source_invoice_id'] = (int) $invoice->id;
                $service->forceFill([
                    'invoice_id' => $invoice->id,
                    'provision_data' => $provisionData,
                ])->save();
            }

            if ($deductBalance && $invoice instanceof Invoice) {
                $lockedUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail($user->id);
                $finance = app(FinanceAccountService::class);
                $amountCents = Money::toCents($amount);

                throw_if(
                    $finance->balanceCents((int) $lockedUser->id, true) < $amountCents,
                    new BusinessException('余额不足，无法创建实例')
                );

                // 余额扣款事实写入账单快照：红字冲抵据此拒绝（冲抵只平账面不返还余额，M-60）。
                // createFromOrder 分支从订单快照继承不到该标记，这里统一补写，保证两条建单路径
                // 口径一致；0 元账单没有实际扣款，不写标记。
                if ($amountCents > 0) {
                    $invoiceSnapshot = is_array($invoice->config_snapshot ?? null) ? $invoice->config_snapshot : [];
                    $invoiceSnapshot['paid_from_balance'] = true;
                    $invoice->forceFill(['config_snapshot' => $invoiceSnapshot])->save();
                }

                if ($amountCents > 0) {
                    $mutation = $finance->debit(
                        (int) $lockedUser->id,
                        $amountCents,
                        FinanceLedgerEventType::INVOICE_PAYMENT,
                        [
                            'source_type' => 'invoice',
                            'source_id' => (int) $invoice->id,
                            'remark' => '支付账单 '.(string) $invoice->invoice_no,
                            'operator_type' => 'admin',
                            'operator_name' => $operatorName !== '' ? $operatorName : null,
                            'trace_id' => $traceId !== '' ? $traceId : null,
                        ],
                    );
                    $balanceTransactionId = $mutation->entryId;
                }

                // 入账联动收敛到生命周期状态机：账单 PAID + 订单投影（同事务单一写者）。
                $invoice = $this->tradeLifecycleService->markInvoicePaid($invoice, [
                    'paid_amount' => $amount,
                    'paid_at' => $now,
                ]);
            }

            if ($invoice instanceof Invoice) {
                $this->invoiceService->syncProjection($invoice);
            }

            // 库存占用统一由开放预留统计承担（新购订单 PENDING/PAID 即占用，取消/完成自动释放），
            // products.stock 为纯上游快照，任何业务路径不得增减。
            return $service;
        });

        $service->loadMissing(['order.invoice', 'invoice']);
        if ($sourceType === 'upstream') {
            $service->loadMissing('product.supplier');
            app(ServiceUpstreamBindingWriter::class)->syncServiceState(
                $service,
                $service->product,
                $this->bindingResolver()->serviceProvisionData($service, includeSecrets: true)
            );
        }

        // 管理员手工开通的服务已落库（概览新增该项），事务提交后失效该用户概览缓存。
        app(ServiceOverviewService::class)->forgetGroupedOverviewCache((int) $user->id);

        try {
            $invoice = $service->invoice ?? $service->order?->invoice;
            $this->operationLogService->write(
                userId: $operatorId > 0 ? $operatorId : null,
                userType: 'admin',
                action: 'order.service.manual_create',
                module: $service->order ? 'order' : 'service',
                targetId: (int) ($service->order?->id ?? $service->id),
                detail: [
                    'order_no' => $service->order?->order_no ?? '',
                    'invoice_id' => (int) ($invoice?->id ?? 0),
                    'invoice_no' => $invoice?->invoice_no ?? '',
                    'service_id' => (int) $service->id,
                    'source_type' => $sourceType,
                    'service_status' => $status,
                    'billing_cycle' => $billingCycle,
                    'amount' => number_format($amount, 2, '.', ''),
                    'create_order' => $createOrder,
                    'create_invoice' => $createInvoice,
                    'deduct_balance' => $deductBalance,
                    'payment_method' => $deductBalance ? 'balance' : null,
                    'balance_transaction_id' => $balanceTransactionId ?: null,
                    'balance_after' => $balanceAfter !== '' ? $balanceAfter : null,
                    'operator_name' => $operatorName,
                    'trace_id' => $traceId,
                ],
                ipAddress: $ipAddress !== '' ? $ipAddress : null,
            );
        } catch (\Throwable $exception) {
            Log::warning('[管理员添加服务] 操作日志写入失败', [
                'user_id' => $user->id,
                'service_id' => $service->id,
                'order_id' => (int) ($service->order?->id ?? 0),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }

        return $this->clientServiceConsoleService->getDetailForUser($user, (int) $service->id, false);
    }

    private function normalizePositiveAmount(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $amount = round((float) $value, 2);

        return $amount > 0 ? $amount : null;
    }

    /**
     * 删除用户服务记录
     */
    public function deleteService(User $user, int $serviceId, array $context = []): void
    {
        $service = Service::query()
            ->with(['product', 'order.invoice'])
            ->where('user_id', $user->id)
            ->findOrFail($serviceId);

        $product = $service->product;
        $order = $service->order;
        $serviceName = trim((string) ($service->name ?: ($product?->name ?? '')));
        $orderNo = trim((string) ($order?->order_no ?? ''));
        $productDisplayName = trim((string) ($product?->name ?? ''));
        $operatorId = (int) (($context['operator_id'] ?? 0) ?: 0);
        $operatorName = trim((string) ($context['operator_name'] ?? ''));
        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $ipAddress = trim((string) ($context['ip_address'] ?? ''));

        DB::transaction(function () use ($service) {
            Order::query()
                ->where('service_id', $service->id)
                ->update(['service_id' => null]);

            Ticket::query()
                ->where('service_id', $service->id)
                ->update(['service_id' => null]);

            // 仅记录型删除不得调用或销毁上游实例。
            DB::table('service_upstream_bindings')
                ->where('service_id', $service->id)
                ->delete();

            // 库存占用由开放预留统计承担：PENDING 订单由支付窗口过期清理自动释放，
            // PAID 订单若需释放库存占用，由管理员显式取消订单，不再回补快照。
            $service->delete();
        });

        // 服务记录已删除（概览移除该项），事务提交后失效该用户概览缓存。
        app(ServiceOverviewService::class)->forgetGroupedOverviewCache((int) $user->id);

        $this->notifyClientServiceTerminated($user, $serviceName);

        try {
            $this->operationLogService->write(
                userId: $user->id,
                userType: 'client',
                action: 'service.record.deleted',
                module: 'service',
                targetId: $serviceId,
                detail: [
                    'service_id' => $serviceId,
                    'service_name' => $serviceName,
                    'product_name' => $productDisplayName,
                    'order_no' => $orderNo,
                    'operator_type' => 'admin',
                    'operator_id' => $operatorId,
                    'operator_name' => $operatorName,
                    'trace_id' => $traceId,
                ],
                ipAddress: $ipAddress !== '' ? $ipAddress : null,
            );

            $this->operationLogService->write(
                userId: $operatorId > 0 ? $operatorId : null,
                userType: 'admin',
                action: 'user.service.record.deleted',
                module: 'service',
                targetId: $serviceId,
                detail: [
                    'target_user_id' => (int) $user->id,
                    'target_user_email' => trim((string) $user->email),
                    'service_name' => $serviceName,
                    'product_name' => $productDisplayName,
                    'order_no' => $orderNo,
                    'operator_name' => $operatorName,
                    'trace_id' => $traceId,
                ],
                ipAddress: $ipAddress !== '' ? $ipAddress : null,
            );
        } catch (\Throwable $exception) {
            Log::warning('[管理员删除服务记录] 操作日志写入失败', [
                'user_id' => $user->id,
                'service_id' => $serviceId,
                'operator_id' => $operatorId,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * 转移服务实例归属：把实例从当前持有用户转移到另一位用户名下。
     *
     * 归属变更只动 services.user_id，订单/账单/跟踪记录仍按原有语义挂原用户；
     * 实例状态不构成转移障碍（运行中 / 暂停 / 已删除 / 异常都可转移），但转移前必须确认
     * 没有待支付或已付未履约的账单，否则新用户查不到旧账单会造成重复收款。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> 接收方视角的实例详情
     */
    public function transferService(User $user, int $serviceId, array $payload, array $context = []): array
    {
        $targetUserId = (int) ($payload['target_user_id'] ?? 0);
        $remark = trim((string) ($payload['remark'] ?? ''));

        // 按当前持有者定位实例：路由参数是裸整数，必须显式带 user_id 约束，避免越权转移他人实例。
        $service = Service::query()
            ->with(['product', 'order'])
            ->where('user_id', $user->id)
            ->findOrFail($serviceId);

        $operatorId = (int) (($context['operator_id'] ?? 0) ?: 0);
        $operatorName = trim((string) ($context['operator_name'] ?? ''));
        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $ipAddress = trim((string) ($context['ip_address'] ?? ''));

        $fromUserId = (int) $service->user_id;
        $product = $service->product;
        $order = $service->order;
        $productDisplayName = $product instanceof Product ? trim((string) $product->name) : '';
        $orderNo = $order instanceof Order ? trim((string) data_get($order, 'order_no')) : '';
        $serviceName = trim((string) $service->name) ?: $productDisplayName;

        if ($targetUserId === $fromUserId) {
            throw new BusinessException('接收方与当前归属用户相同，无需转移');
        }

        // 接收方必须是未软删且启用中的用户：禁用账号仍会被自动续费扣款，但不能承接新资产。
        $targetUser = User::query()->active()->find($targetUserId);
        if (! $targetUser instanceof User) {
            throw new BusinessException('接收方用户不存在或已被禁用');
        }

        $fromUserEmail = trim((string) $user->email);
        $toUserEmail = trim((string) $targetUser->email);

        // 事务外先按内存快照校验一次，让常见失败尽早返回且不产生任何写入；
        // 最终判定以事务内的锁后复查为准（见下方 assertServiceTransferable($locked, true)）。
        $this->assertServiceTransferable($service);

        // 不写 summary：交给 ServiceTransformService::resolveOperationSummary() 按
        // 'service.console.transfer' 生成「实例归属已转移给{接收方邮箱}」，比固定文案信息量更高。
        $logDetail = [
            'category' => 'service',
            'service_id' => (int) $service->id,
            'service_name' => $serviceName,
            'product_name' => $productDisplayName,
            'order_no' => $orderNo,
            'from_user_id' => $fromUserId,
            'from_user_email' => $fromUserEmail,
            'to_user_id' => (int) $targetUser->id,
            'to_user_email' => $toUserEmail,
            'remark' => $remark,
            'operator_type' => 'admin',
            'operator_id' => $operatorId,
            'operator_name' => $operatorName,
            'trace_id' => $traceId,
        ];

        $serviceIdValue = (int) $service->id;

        // 事务内重锁实例并复核持有者，防并发双提交把同一实例转移到不同接收方；
        // 账单必须在锁内再校验一次：续费建单同样锁 services 行，只在锁外判定
        // 会让窗口内新落库的「待支付 / 已付未履约」账单被漏放行。
        //
        // 审计日志一并纳入本事务，并用 writeStrict()/strict 模式写入：归属变更是强审计
        // 事件，日志没落库等于「改了归属但查不到是谁改的」，此时整笔转移必须回滚。
        // 默认 write() 会吞掉写入异常（日志失败不拖垮业务），故这里不能用它。
        $freshService = DB::transaction(function () use (
            $targetUser,
            $fromUserId,
            $serviceIdValue,
            $logDetail,
            $operatorId,
            $operatorName,
            $traceId,
            $ipAddress,
        ): Service {
            $locked = Service::query()->lockForUpdate()->find($serviceIdValue);

            if (! $locked instanceof Service) {
                throw new BusinessException('实例不存在或已被删除', 40400, 404);
            }

            // 归属已被并发修改：同一实例不能同时转给两个人，后到者按并发冲突处理。
            // 「接收方等于当前持有者」在锁外已拦（此处再判是死代码），重复提交也只会
            // 落到本分支——因为上一步的 $fromUserId 一定是转移前的持有者。
            if ((int) $locked->user_id !== $fromUserId) {
                throw new BusinessException('实例归属已被其他操作变更，请刷新后重试', 40900, 409);
            }

            // 锁内复查账单并对命中的账单行加锁，杜绝「校验通过 → 并发建单」的空窗。
            $this->assertServiceTransferable($locked, true);

            // 自动续费开关必须一起重置：AutoRenewService 按 $service->user 预览并从该账户扣款，
            // 只换 user_id 会让新持有人在自己没有同意的情况下被扣费。
            $autoRenewReset = (int) $locked->auto_renew === 1;

            $locked->forceFill([
                'user_id' => (int) $targetUser->id,
                'auto_renew' => 0,
            ])->save();

            $loggedService = $locked->refresh()->loadMissing(['product', 'order']);

            $auditDetail = $logDetail;
            if ($autoRenewReset) {
                $auditDetail['auto_renew_reset'] = true;
            }

            // 实例操作日志：显式声明 actor_type=admin，否则会被回落到服务当前持有者（新用户）身上。
            $this->operationLogService->writeServiceConsoleLog(
                $loggedService,
                'service.console.transfer',
                $auditDetail,
                [
                    'actor_type' => 'admin',
                    'actor_user_id' => $operatorId,
                    'actor_name' => $operatorName,
                    'trace_id' => $traceId,
                    'ip_address' => $ipAddress,
                ],
                strict: true,
            );

            // 客户侧流水会进入双方实例操作日志，裁剪运营侧身份与来源，避免把管理端账号名、IP 下发给客户。
            // actor_type 显式写 client：留空虽会按 client 回落，但 ServiceTransformService 会继续
            // 拿 operator_name 当客户名；而写成 admin 会被客户端可见性过滤（只保留非 admin 记录）。
            $clientLogDetail = $auditDetail;
            unset($clientLogDetail['operator_type'], $clientLogDetail['operator_id'], $clientLogDetail['operator_name'], $clientLogDetail['trace_id']);
            $clientLogDetail['actor_type'] = 'client';

            $this->operationLogService->writeStrict(
                userId: $fromUserId,
                userType: 'client',
                action: 'service.record.transferred_out',
                module: 'service',
                targetId: $serviceIdValue,
                detail: $clientLogDetail,
                ipAddress: null,
            );

            $this->operationLogService->writeStrict(
                userId: (int) $targetUser->id,
                userType: 'client',
                action: 'service.record.transferred_in',
                module: 'service',
                targetId: $serviceIdValue,
                detail: $clientLogDetail,
                ipAddress: null,
            );

            $this->operationLogService->writeStrict(
                userId: $operatorId > 0 ? $operatorId : null,
                userType: 'admin',
                action: 'user.service.record.transferred',
                module: 'service',
                targetId: $serviceIdValue,
                detail: $logDetail,
                ipAddress: $ipAddress !== '' ? $ipAddress : null,
            );

            return $loggedService;
        });

        // 缓存键同时含 user_id 与 updated_at：此时 $service 仍是转移前的内存快照，
        // 用它清掉旧持有者的详情/配置/远程状态缓存；再用事务内刷新后的实例清新键。
        app(ServiceDetailService::class)->forgetDetailCaches($service);
        app(ServiceDetailService::class)->forgetDetailCaches($freshService);

        // 概览缓存按用户分组，新旧两个用户都要失效，否则列表仍显示旧归属。
        ServiceOverviewService::forgetGroupedOverviewCacheFor($fromUserId);
        ServiceOverviewService::forgetGroupedOverviewCacheFor((int) $targetUser->id);

        // 双方都要收到通知：原持有者的实例凭空消失、接收方凭空多一台机器，
        // 且接收方此后成为续费主体。邮件走队列（SendTemplateEmailJob 已 afterCommit），
        // 发送失败不影响已提交的转移。
        $this->notifyServiceTransferred($user, $targetUser, $serviceName, $remark);

        // 事务已提交后只回本地快照：getDetailForUser() 在需要刷新时会同步请求上游，
        // 超时会让「已经转移成功」显示成失败，重试又会因路径已不属于原用户而 404。
        return $this->clientServiceConsoleService->getBaseDetailForUser($targetUser, $serviceIdValue);
    }

    /**
     * 实例账务前置校验：存在未结清账单时拒绝转移。
     *
     * 不对实例状态做任何限制：运行中、暂停、已删除以及异常等状态下都允许转移归属。
     * 状态限制只影响「这台机器当前能不能用」，不影响「这台机器归谁」，把它当成转移门槛
     * 会让已删除实例永远卡在原用户名下。真正的资金风险来自账单，故只保留账单校验。
     *
     * @param  bool  $forUpdate  调用方已在事务内并锁住实例行时为 true：命中的账单行一并加锁，
     *                           否则"校验通过 → 并发建单"之间仍有空窗。
     */
    private function assertServiceTransferable(Service $service, bool $forUpdate = false): void
    {
        $serviceId = (int) $service->id;

        $unpaidQuery = Invoice::query()
            ->where('service_id', $serviceId)
            ->where('status', InvoiceStatus::UNPAID)
            ->latest('id');

        if ($forUpdate) {
            $unpaidQuery->lockForUpdate();
        }

        $unpaid = $unpaidQuery->first();

        if ($unpaid instanceof Invoice) {
            throw new BusinessException('该实例存在待支付账单（'.trim((string) $unpaid->invoice_no).'），请先结清或作废后再转移');
        }

        // 已付未履约：账单归属仍在原用户名下，转移后新用户查不到这笔收款，同周期可再次下单造成双扣。
        // 这里不能按 requires_refund=false 放行：标记人工退款只代表"将来要退"，钱已收、履约未完成，
        // 放行会让续费履约直接落到新持有者名下，退款与权益归属分叉。
        $paidPendingQuery = Invoice::query()
            ->where('service_id', $serviceId)
            ->where('status', InvoiceStatus::PAID)
            ->where('fulfillment_pending', true)
            ->latest('id');

        if ($forUpdate) {
            $paidPendingQuery->lockForUpdate();
        }

        $paidPending = $paidPendingQuery->first();

        if ($paidPending instanceof Invoice) {
            throw new BusinessException('该实例存在已支付但尚未履约完成的账单（'.trim((string) $paidPending->invoice_no).'），请待履约完成或退款到账后再转移');
        }
    }

    private function supportsManagedUpstream(?Product $product): bool
    {
        if (! $product instanceof Product) {
            return false;
        }

        $supplier = $this->bindingResolver()->supplierForProduct($product);

        if ($supplier instanceof Supplier) {
            $supplier = $this->bindingResolver()->supplierWithRuntimeCredentials($supplier);
            app(UpstreamBindingWriter::class)->syncSupplierBinding($supplier);
            app(UpstreamBindingWriter::class)->syncProductBinding($product, $supplier);
        }

        return $supplier instanceof Supplier
            && app(ProviderResolver::class)->resolveForSupplier($supplier)->supports(ProvidesConsoleRuntime::class);
    }

    /**
     * @param  array<string, mixed>  $provisionData
     */
    private function resolveServiceProviderKey(Service $service, array $provisionData): string
    {
        $providerKey = $this->bindingResolver()->providerKeyForService($service);
        $providerKey ??= $service->product instanceof Product ? $this->resolveProductProviderKey($service->product) : null;

        return trim((string) $providerKey);
    }

    private function resolveProductProviderKey(?Product $product): string
    {
        if (! $product instanceof Product) {
            return '';
        }

        $providerKey = $this->bindingResolver()->providerKeyForProduct($product);

        return trim((string) $providerKey);
    }

    private function resolveSupplierProviderKey(Supplier $supplier): string
    {
        $providerKey = $this->bindingResolver()->providerKeyForSupplier($supplier);

        return trim((string) ($providerKey ?? ''));
    }

    /**
     * @param  array<string, mixed>  $provisionData
     */
    private function resolveServiceSupplierId(Service $service, array $provisionData): int
    {
        $supplierId = $this->bindingResolver()->supplierIdForService($service);
        $supplierId ??= $service->product instanceof Product ? $this->resolveProductSupplierId($service->product) : null;

        return (int) ($supplierId ?? 0);
    }

    private function resolveProductSupplierId(?Product $product): int
    {
        if (! $product instanceof Product) {
            return 0;
        }

        $supplierId = $this->bindingResolver()->supplierIdForProduct($product);

        return (int) ($supplierId ?? 0);
    }

    /**
     * @param  array<string, mixed>  $provisionData
     */
    private function resolveServiceUpstreamProductId(Service $service, array $provisionData): int
    {
        $upstreamProductId = $this->bindingResolver()->upstreamProductIdForService($service);
        $upstreamProductId ??= $service->product instanceof Product ? $this->resolveProductUpstreamProductId($service->product) : null;

        return (int) ($upstreamProductId ?? 0);
    }

    private function resolveProductUpstreamProductId(?Product $product): int
    {
        if (! $product instanceof Product) {
            return 0;
        }

        $upstreamProductId = $this->positiveInt($this->bindingResolver()->upstreamProductIdForProduct($product));

        return (int) ($upstreamProductId ?? 0);
    }

    /**
     * @param  array<string, mixed>  $provisionData
     */
    private function resolveServiceUpstreamHostId(Service $service, array $provisionData): int
    {
        return (int) ($this->positiveInt($this->bindingResolver()->upstreamServiceIdForService($service)) ?? 0);
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function nonBlank(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function bindingResolver(): PluginBindingResolver
    {
        // 请求内复用同一实例：绑定解析可命中批量预载写入的行级缓存
        return $this->bindingResolver ??= app(PluginBindingResolver::class);
    }

    private function resolveManualServiceExpiresAt(mixed $expiresAt, string $billingCycle, int $status): ?Carbon
    {
        if ($expiresAt !== null && $expiresAt !== '') {
            return Carbon::parse((string) $expiresAt);
        }

        if ($status === ServiceStatus::TERMINATED) {
            return null;
        }

        // 到期推进统一走 BillingCycle::advance（夹月末不溢出）；未知周期返回 null
        return BillingCycle::advance(now(), $billingCycle);
    }

    private function resolveManualServiceName(Product $product, array $data, string $domain): string
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        if ($domain !== '') {
            return $domain;
        }

        return trim((string) $product->name) !== '' ? (string) $product->name : '未命名服务';
    }

    private function buildConnectionSecret(array $connection): ?string
    {
        $payload = [
            'hostname' => trim((string) ($connection['hostname'] ?? '')),
            'username' => trim((string) ($connection['username'] ?? '')),
            'password' => (string) ($connection['password'] ?? ''),
            'port' => (int) (($connection['port'] ?? 0) ?: 0),
            'internal_ip' => trim((string) ($connection['internal_ip'] ?? '')),
        ];

        $hasValue = collect($payload)->contains(fn ($value) => ! in_array($value, ['', null, 0], true));
        if (! $hasValue) {
            return null;
        }

        return Crypt::encryptString((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * 管理员删除服务记录后的客户通知（模板 100008）：队列异步发送，
     * 失败仅告警不阻断删除流程；无邮箱（纯手机号账号）静默跳过。
     */
    private function notifyClientServiceTerminated(User $user, string $serviceName): void
    {
        $email = trim((string) $user->email);
        if ($email === '') {
            return;
        }

        try {
            $terminatedAt = now()->format('Y-m-d H:i:s');
            $displayServiceName = $serviceName !== '' ? $serviceName : '您的产品';
            $terminatedNotice = '删除操作完成后资源可能无法恢复，如有疑问请联系客服。';

            SendTemplateEmailJob::dispatch($email, NotificationService::TEMPLATE_SERVICE_TERMINATED, [
                'display_name' => trim((string) ($user->display_name ?? '')) !== '' ? (string) $user->display_name : $email,
                'service_name' => $displayServiceName,
                'terminated_at' => $terminatedAt,
                'notice_message' => $terminatedNotice,
            ]);

            // 站内消息：受管理端「服务删除」场景的站内消息开关控制
            app(UserNotificationService::class)->createForScene(
                (int) $user->id,
                'service_terminated',
                '服务已删除',
                "您的服务「{$displayServiceName}」已删除。{$terminatedNotice}",
                '/client/services',
                ['service_name' => $displayServiceName, 'notice_message' => $terminatedNotice],
                []
            );
        } catch (\Throwable $exception) {
            Log::warning('[管理员删除服务记录] 客户通知派发失败', [
                'user_id' => (int) $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 归属转移后通知双方（模板 100030）：原持有者失去实例，接收方获得实例并成为后续续费主体。
     *
     * 邮件走队列（SendTemplateEmailJob 构造内已 afterCommit），派发失败只告警，不影响已提交的转移。
     */
    private function notifyServiceTransferred(User $fromUser, User $toUser, string $serviceName, string $remark): void
    {
        $pairs = [
            [$fromUser, $toUser, 'out', '转出'],
            [$toUser, $fromUser, 'in', '转入'],
        ];

        foreach ($pairs as [$recipient, $counterparty, $direction, $directionLabel]) {
            $email = trim((string) $recipient->email);
            if ($email === '') {
                continue;
            }

            try {
                $displayServiceName = $serviceName !== '' ? $serviceName : '您的产品';
                $transferNotice = $direction === 'out'
                    ? '该实例已不在您的账号下，历史订单与账单仍保留在原账号；如需继续使用请重新下单。'
                    : '该实例已转入您的账号，后续续费由您的账号承担；历史订单与账单仍保留在原持有人账号。';

                SendTemplateEmailJob::dispatch($email, NotificationService::TEMPLATE_SERVICE_TRANSFERRED, [
                    'display_name' => trim((string) ($recipient->display_name ?? '')) !== '' ? (string) $recipient->display_name : $email,
                    'service_name' => $displayServiceName,
                    'direction_label' => $directionLabel,
                    'counterparty_email' => trim((string) $counterparty->email),
                    'remark' => $remark,
                    'transferred_at' => now()->format('Y-m-d H:i:s'),
                    'notice_message' => $transferNotice,
                ]);

                // 站内消息：受管理端「实例归属变更」场景的站内消息开关控制
                app(UserNotificationService::class)->createForScene(
                    (int) $recipient->id,
                    'service_transferred',
                    '实例归属已变更',
                    "您的实例「{$displayServiceName}」归属已变更（{$directionLabel}），对方账号 ".trim((string) $counterparty->email).'。',
                    '/client/services',
                    [
                        'service_name' => $displayServiceName,
                        'direction_label' => $directionLabel,
                        'counterparty_email' => trim((string) $counterparty->email),
                    ],
                    []
                );
            } catch (\Throwable $exception) {
                Log::warning('[管理员转移实例归属] 客户通知派发失败', [
                    'user_id' => (int) $recipient->id,
                    'direction' => $direction,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
