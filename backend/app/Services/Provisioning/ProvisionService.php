<?php

namespace App\Services\Provisioning;

use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\ProvisionLeaseBusyException;
use App\Exceptions\UpstreamStockShortageException;
use App\Jobs\SendTemplateEmailJob;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceOverviewService;
use App\Services\Integrations\Plugins\PluginBindingResolver;
use App\Services\Integrations\Plugins\ServiceUpstreamBindingWriter;
use App\Services\Integrations\Plugins\UpstreamBindingWriter;
use App\Services\Integrations\Support\ProviderErrorMapper;
use App\Services\ProductCatalog\ProductCatalogService;
use App\Services\ProductCatalog\ProductSyncService;
use App\Services\System\NotificationService;
use App\Services\System\SettingService;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\ProviderResolver;
use App\Support\BillingCycle;
use App\Support\BillingCycleLabels;
use App\Support\Money;
use App\Support\ProductProvisionHostname;
use App\Support\SchemaMetadataCache;
use App\Support\ServiceHostname;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProvisionService
{
    private const RANGE_TYPES = [4, 7, 9, 11, 14, 15, 16, 17, 18, 19];

    private const OS_TYPES = [5];

    private const TYPE_FIELD_MAP = [
        4 => 'ip_num',
        5 => 'os',
        6 => 'cpu',
        7 => 'cpu',
        8 => 'memory',
        9 => 'memory',
        10 => 'bw',
        11 => 'bw',
        12 => 'area',
        13 => 'system_disk_size',
        14 => 'system_disk_size',
        16 => 'cpu',
        17 => 'memory',
        18 => 'bw',
        19 => 'system_disk_size',
    ];

    /**
     * 实例级开通租约的初始时长（秒）。
     *
     * 它只覆盖「抢占 → 进入上游调用」这一瞬，不负责撑满整段 checkout：进入上游前
     * 会调 renewProvisionLease() 把到期时间推到购物车锁同口径（默认 1260 秒），
     * 避免通用驱动串行 HTTP（清购物车→加购→checkout→fund→查实例）跑到一半租约过期、
     * 被第三个执行者接管后对同一实例重复下单。租约过期后仍允许接管，旧执行者靠
     * 回写前的令牌比对自我否决。
     */
    private const PROVISION_LEASE_SECONDS = 180;

    private ?ServiceUpstreamBindingWriter $serviceBindingWriter = null;

    public function __construct(
        private ProviderResolver $providerResolver,
        private SettingService $settingService,
        private ?ProductCatalogService $productCatalogService = null,
        private ?PluginBindingResolver $bindingResolver = null,
        private ?ProductSyncService $productSyncService = null,
    ) {}

    private function productCatalogService(): ProductCatalogService
    {
        return $this->productCatalogService ??= app(ProductCatalogService::class);
    }

    /**
     * 使用 app() 延迟解析：本服务在测试中经 new 直接实例化，避免新增构造依赖。
     */
    private function productSyncService(): ProductSyncService
    {
        return $this->productSyncService ??= app(ProductSyncService::class);
    }

    public function processPaidOrder(Order $order): ?Service
    {
        $order->loadMissing(['product.supplier', 'user', 'service']);

        if (! $order->exists || $order->type !== 'new' || ! $order->product) {
            return null;
        }

        $service = $this->ensureLocalService($order);

        // 管理员手动开通的服务在建单时就已就绪，事后付款只需完成订单，
        // 不能因为商品非自动开通就把已激活的服务打回待开通。
        if ($this->isAdminManualOrder($order) && $service->status !== ServiceStatus::PENDING) {
            $order->forceFill([
                'service_id' => $service->id,
                'service_snapshot' => $this->buildServiceSnapshot($service),
            ])->save();

            return $service;
        }

        if (! $this->shouldAutoProvision($order->product, $service)) {
            $this->markPending($order, $service, '待人工开通');

            return $service;
        }

        if ($service->status === ServiceStatus::ACTIVE && $this->resolveServiceUpstreamServiceId($service) !== null) {
            $order->forceFill([
                'service_id' => $service->id,
                'service_snapshot' => $this->buildServiceSnapshot($service),
            ])->save();

            return $service;
        }

        $this->assertNoUnresolvedUpstreamProvisionInvoice($service);

        // 首次提交前冻结端点与标识：本单后续的所有重试都必须打回同一上游，
        // 否则后台改绑供应商后，重试会对着新端点重新下单。
        $this->freezeProvisionCredentialSnapshot($order, $service);

        return $this->submitUpstreamProvision($order, $service);
    }

    /**
     * 基于账单直接开通（无订单场景的过渡桥接）
     * 优先尝试从 invoice->order 委托，若无 order 则用 invoice 自带字段构造上下文。
     */
    public function processPaidInvoice(Invoice $invoice): ?Service
    {
        $invoice->loadMissing(['order.product.supplier', 'order.user', 'order.service', 'product.supplier']);

        if ($invoice->order) {
            return $this->processPaidOrder($invoice->order);
        }

        if (! $invoice->product_id || (string) ($invoice->type ?? '') !== 'normal') {
            return null;
        }

        $product = $invoice->product;
        if (! $product) {
            return null;
        }

        Log::info('[自动开通] 基于账单开通（无关联订单）', [
            'invoice_id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'product_id' => $product->id,
        ]);

        // 开通基数用目录价（应付价 + 券减免 + 会员折扣减免）：折后价进入续费定价会被再次打折，逐轮复利衰减。
        $catalogAmount = Money::catalogAmountOf($invoice);

        $service = Service::create([
            'user_id' => $invoice->user_id,
            'product_id' => $invoice->product_id,
            'name' => $invoice->display_product_name ?: '未命名服务',
            'domain' => '',
            'billing_cycle' => (string) ($invoice->billing_cycle ?? ''),
            'amount' => $catalogAmount,
            'locked_pricing' => Service::buildDefaultRenewPricing(
                is_array($product->pricing ?? null) ? $product->pricing : [],
                (string) ($invoice->billing_cycle ?? ''),
                $catalogAmount
            ),
            'status' => ServiceStatus::PENDING,
            'auto_renew' => 0,
            'provision_data' => [
                'created_from_invoice' => $invoice->invoice_no,
            ],
        ]);

        $invoice->forceFill(['service_id' => $service->id])->save();

        $this->applyRenewCouponBinding($service, $invoice);

        // 新服务记录已落库（概览会新增该服务），事务外失效该用户概览缓存，避免最长 120s 脏读。
        $this->forgetServiceOverviewCache((int) $invoice->user_id);

        return $service;
    }

    public function retryFailedProvision(Order $order): Service
    {
        $order->loadMissing(['product.supplier', 'user', 'service']);

        if (! $order->exists || $order->type !== 'new' || ! $order->product) {
            throw new BusinessException('订单未关联可自动开通商品，无法重新提交上游购买');
        }

        $service = $this->ensureLocalService($order);

        if (! $this->shouldAutoProvision($order->product, $service)) {
            throw new BusinessException('当前商品未接入自动开通上游，无法重新提交上游购买');
        }

        if ($service->status === ServiceStatus::ACTIVE && $this->resolveServiceUpstreamServiceId($service) !== null) {
            return $service;
        }

        $this->assertNoUnresolvedUpstreamProvisionInvoice($service);

        // 租约必须在任何状态改动之前抢到：prepareServiceForProvisionRetry 会把服务打回 PENDING
        // 并清空 suspended_reason。若先改状态再在 submitUpstreamProvision 里发现租约被占，
        // 管理员会看到「重试失败」但人工暂停的服务已被改成待开通，暂停原因也丢了。
        $leaseToken = $this->acquireProvisionLease($service);
        if ($leaseToken === null) {
            Log::warning('[自动开通] 实例开通租约被占用，跳过本次重试', [
                'order_id' => (int) $order->id,
                'service_id' => (int) $service->id,
            ]);

            throw new ProvisionLeaseBusyException;
        }

        try {
            $this->prepareServiceForProvisionRetry($order, $service);

            // 重试沿用首次快照：prepareServiceForProvisionRetry 只清理运行态键，不动 credential_snapshot。
            $this->freezeProvisionCredentialSnapshot($order, $service);
        } catch (\Throwable $exception) {
            // 状态准备阶段失败同样要归还租约，否则服务会被挂到租约自然过期，
            // 期间所有执行者都只能收到 409。
            $this->releaseProvisionLease($service, $leaseToken);

            throw $exception;
        }

        return $this->submitUpstreamProvision($order, $service, true, $leaseToken);
    }

    public function retryFailedProvisionByInvoice(Invoice $invoice): Service
    {
        $invoice->loadMissing(['order', 'service']);

        if ($invoice->order instanceof Order) {
            return $this->retryFailedProvision($invoice->order);
        }

        throw new BusinessException('账单未关联订单，暂不支持重新提交上游开通');
    }

    /**
     * @param  string|null  $leaseToken  调用方已抢占的租约；为 null 时由本方法自行抢占
     */
    private function submitUpstreamProvision(Order $order, Service $service, bool $throwOnFailure = true, ?string $leaseToken = null): Service
    {
        // 实例级开通租约：订单锁只串行化「支付后同步履约」这一条入口，失败补偿命令、
        // 人工重试与上游回调都可能绕过它并发提交同一实例，导致重复 checkout。
        // 租约必须在改服务状态之前抢占：抢不到说明已有执行者正在开通，不能再把服务打回 PENDING。
        if ($leaseToken === null) {
            $leaseToken = $this->acquireProvisionLease($service);
        }
        if ($leaseToken === null) {
            Log::warning('[自动开通] 实例开通租约被占用，跳过本次提交', [
                'order_id' => (int) $order->id,
                'service_id' => (int) $service->id,
            ]);

            // 必须抛而不是静默返回：调用方（管理员手动重试、补偿命令、支付后履约）
            // 都是「调用成功即认为已提交」。静默返回会让管理员界面上显示开通已提交、
            // 操作日志记 submitted，实际一次上游请求都没发。租约被占是「本次没做」，
            // 要用可识别的异常让它落成 failed 并提示稍后重试。
            throw new ProvisionLeaseBusyException;
        }

        // 租约续期：初始 180 秒只够抢占，上游 checkout 最坏耗时可能到数分钟。
        // 进入上游调用前把到期时间推到购物车锁同口径（默认 1260 秒），
        // 保证整个上游窗口内租约有效，不会出现「还在 checkout，租约已过期」的假接管。
        // 必须放在 markPending 之前：它会把人工暂停的服务打回 PENDING 并清空 suspended_reason，
        // 续期失败时若已经改过状态，就等于白改一次业务状态再抛错。
        if (! $this->renewProvisionLease($service, $leaseToken)) {
            Log::warning('[自动开通] 实例开通租约在进入上游前已失效，放弃本次提交', [
                'order_id' => (int) $order->id,
                'service_id' => (int) $service->id,
            ]);

            throw new ProvisionLeaseBusyException;
        }

        $providerKey = '';

        try {
            // 改业务状态与解析驱动类也必须留在 try 内：它们会写库、会走容器解析，
            // 一旦在这里抛异常而租约还没进 finally，这个实例就会被挂到租约自然过期，
            // 期间所有执行者只能收到 409。
            $this->markPending($order, $service);
            $providerKey = $this->resolveProviderKeyForProduct($order->product, $service);

            // 第二遍库存闸门：提交上游购物车结算前按上游实时库存复核（排除本单预留额度），
            // 上游已无货时直接拦截，不再空走清购物车→加购→checkout 流程；失败统一落 provision_error。
            if ($order->product instanceof Product) {
                $this->productCatalogService()->assertUpstreamStockForProvision($order->product, (int) $order->id);
            }

            $result = $this->provisionViaUpstream($order, $service);
            $hostDetail = $result['host_detail'];
            $serviceStatus = $this->resolveServiceStatusFromUpstream($hostDetail);

            // 回写前自证租约：上游 checkout 可能超过 180 秒，过期后他人已接管。
            // 此时旧执行者写状态会把接管者已开通的结果覆盖回旧配置；但上游账单/实例标识
            // 是防重复下单的唯一凭据，仍必须落库，所以只放弃服务状态回写。
            if (! $this->provisionLeaseHeld($service, $leaseToken)) {
                Log::error('[自动开通] 租约已失效且有其他执行者接管，放弃本次服务状态回写', [
                    'order_id' => (int) $order->id,
                    'service_id' => (int) $service->id,
                ]);
                $this->persistPartialUpstreamProvisionIdentifiers(
                    $service,
                    (int) ($result['upstream_invoice_id'] ?? 0),
                    (array) ($result['upstream_host_ids'] ?? []),
                    (int) $order->id
                );

                return $service->fresh() ?? $service;
            }

            $service->forceFill([
                'status' => $serviceStatus,
                'name' => (string) ($service->name ?: $order->display_product_name ?: $order->product?->name ?: '未命名服务'),
                'domain' => (string) ($hostDetail['domain'] ?? $result['requested_host']),
                'expires_at' => $this->resolveServiceExpiry($hostDetail, $order),
                'suspended_reason' => null,
                'provision_data' => array_merge($this->serviceProvisionData($service), [
                    'provider_key' => $providerKey,
                    'supplier_id' => $this->resolveProductSupplierId($order->product, $service),
                    'requested_host' => $result['requested_host'],
                    'upstream_invoice_id' => $result['upstream_invoice_id'],
                    'upstream_host_id' => $result['upstream_host_id'],
                    'upstream_host_ids' => $result['upstream_host_ids'],
                    'upstream_product_id' => $this->resolveUpstreamProductId($order->product, $service),
                    'upstream_product_name' => trim((string) ($hostDetail['product_name'] ?? '')),
                    'upstream_status' => (string) ($hostDetail['domainstatus'] ?? ''),
                    'dedicated_ip' => (string) ($hostDetail['dedicatedip'] ?? ''),
                    'assigned_ips' => is_array($hostDetail['assignedips'] ?? null) ? $hostDetail['assignedips'] : [],
                    'host_config_option' => is_array($hostDetail['config_option'] ?? null) ? $hostDetail['config_option'] : [],
                    'os' => (string) ($hostDetail['os'] ?? ''),
                    'connection_secret' => $this->encryptConnectionCache([
                        'hostname' => (string) ($hostDetail['domain'] ?? $result['requested_host']),
                        'username' => (string) ($hostDetail['username'] ?? ''),
                        'password' => (string) ($hostDetail['password'] ?? ''),
                        'port' => (int) (($hostDetail['port'] ?? 0) ?: 0),
                        'internal_ip' => (string) ($hostDetail['internalip'] ?? $hostDetail['privateip'] ?? ''),
                    ]),
                    'connection_cached_at' => now()->format('Y-m-d H:i:s'),
                    'last_provisioned_at' => now()->format('Y-m-d H:i:s'),
                    'provision_error' => null,
                ]),
            ])->save();
            // 上游开通成功，服务状态/到期时间/名称等概览字段已更新，失效该用户概览缓存。
            $this->forgetServiceOverviewCache((int) $order->user_id);
            $this->serviceBindingWriter()->recordProvisionAttempt(
                $service,
                $order->product,
                (array) ($service->provision_data ?? []),
                'success',
                null,
                [
                    'order_id' => (int) $order->id,
                    'order_no' => (string) $order->order_no,
                    'supplier_id' => $this->resolveProductSupplierId($order->product, $service),
                    'upstream_product_id' => $this->resolveUpstreamProductId($order->product, $service),
                    'requested_host' => $result['requested_host'] ?? null,
                ],
                [
                    'upstream_invoice_id' => $result['upstream_invoice_id'] ?? null,
                    'upstream_host_id' => $result['upstream_host_id'] ?? null,
                    'upstream_host_ids' => $result['upstream_host_ids'] ?? null,
                ]
            );

            $this->notifyClientServiceActivated($order, $service, (array) $hostDetail);

            $order->forceFill([
                'service_id' => $service->id,
                'service_snapshot' => $this->buildServiceSnapshot($service),
            ])->save();

            return $service;
        } catch (\Throwable $exception) {
            $message = $exception instanceof BusinessException
                ? $exception->getMessage()
                : '上游开通失败，请检查供应商配置或日志';

            // 失败回写同样要自证租约：接管者可能已开通成功，旧执行者把状态写回 PENDING
            // 会覆盖掉已经生效的结果。上游账单/实例标识由 provisionViaUpstream 的
            // 部分成功持久化落库，不在这里丢弃。
            if (! $this->provisionLeaseHeld($service, $leaseToken)) {
                Log::error('[自动开通] 租约已失效且有其他执行者接管，放弃本次失败状态回写', [
                    'order_id' => (int) $order->id,
                    'service_id' => (int) $service->id,
                    'message' => $message,
                ]);

                if ($throwOnFailure) {
                    throw $exception instanceof BusinessException
                        ? $exception
                        : new BusinessException($message);
                }

                return $service->fresh() ?? $service;
            }

            // 失败回写必须保住首次冻结的端点快照（serviceProvisionData 已带出 legacy 里的快照），
            // 否则一次失败就会把快照冲掉，重试会打回改绑后的新上游。
            $failedProvisionData = $this->serviceProvisionData($service);
            // markPending/驱动类解析本身失败时 $providerKey 还没赋值，这里回落到实时解析，
            // 避免把 provision_data.provider_key 写成空串冲掉已有标识。
            $fallbackProduct = $order->product instanceof Product ? $order->product : null;
            $failedProvisionData['provider_key'] = $providerKey !== ''
                ? $providerKey
                : $this->resolveProviderKeyForProduct($fallbackProduct, $service);
            $failedProvisionData['supplier_id'] = $this->resolveProductSupplierId($order->product, $service);
            $failedProvisionData['upstream_product_id'] = $this->resolveUpstreamProductId($order->product, $service);
            $failedProvisionData['last_provision_attempt_at'] = now()->format('Y-m-d H:i:s');
            $failedProvisionData['provision_error'] = $message;

            $service->forceFill([
                'status' => ServiceStatus::PENDING,
                'suspended_reason' => mb_substr($message, 0, 200),
                'provision_data' => $failedProvisionData,
            ])->save();
            $this->serviceBindingWriter()->recordProvisionAttempt(
                $service,
                $order->product,
                (array) ($service->provision_data ?? []),
                'failed',
                $message,
                [
                    'order_id' => (int) $order->id,
                    'order_no' => (string) $order->order_no,
                    'supplier_id' => $this->resolveProductSupplierId($order->product, $service),
                    'upstream_product_id' => $this->resolveUpstreamProductId($order->product, $service),
                ]
            );

            $order->forceFill([
                'service_id' => $service->id,
                'service_snapshot' => $this->buildServiceSnapshot($service),
            ])->save();

            Log::error('[自动开通] 支付后上游开通失败', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'service_id' => $service->id,
                'supplier_id' => $this->resolveProductSupplierId($order->product, $service),
                'upstream_product_id' => $this->resolveUpstreamProductId($order->product, $service),
                'message' => $message,
                'exception' => $exception::class,
            ]);

            if ($throwOnFailure) {
                throw $exception instanceof BusinessException
                    ? $exception
                    : new BusinessException($message);
            }

            return $service;
        } finally {
            // 状态与审计流水写完后再释放租约，让后续重试与运维操作能立即接管；
            // 只清理仍属于本次执行的令牌，接管者的租约不会被误删。
            $this->releaseProvisionLease($service, $leaseToken);
        }
    }

    /**
     * 产品开通成功客户通知（模板 100005）。
     * retryFailedProvision 与队列重试可能多次进入成功分支，以 AutomationLog 幂等；
     * 发送失败仅告警，不影响开通结果。管理员手工开通（manualProvisionService）不走本方法。
     */
    private function notifyClientServiceActivated(Order $order, Service $service, array $hostDetail): void
    {
        $user = User::query()->find((int) $order->user_id);
        $email = trim((string) ($user?->email ?? ''));
        if ($email === '') {
            return;
        }

        if (! AutomationLog::recordOnce(
            'provisioning-notify', 'service_activated', 'service', (int) $service->id, 'activated'
        )) {
            return;
        }

        try {
            SendTemplateEmailJob::dispatch($email, NotificationService::TEMPLATE_SERVICE_ACTIVATED, [
                'display_name' => trim((string) ($user?->display_name ?? '')) !== '' ? (string) $user->display_name : $email,
                'service_name' => (string) ($service->name ?: $order->display_product_name),
                'hostname' => (string) ($service->domain ?? $hostDetail['domain'] ?? ''),
                'product_main_ip' => (string) ($hostDetail['dedicatedip'] ?? ''),
                'product_username' => (string) ($hostDetail['username'] ?? ''),
                'operating_system' => (string) ($hostDetail['os'] ?? ''),
                'activated_at' => now()->format('Y-m-d H:i:s'),
                'expires_at' => $service->expires_at instanceof Carbon
                    ? $service->expires_at->format('Y-m-d H:i:s')
                    : '-',
                'billing_cycle_label' => BillingCycleLabels::label((string) ($order->billing_cycle ?? '')),
            ]);
        } catch (\Throwable $exception) {
            AutomationLog::forgetRecord(
                'provisioning-notify', 'service_activated', 'service', (int) $service->id, 'activated'
            );
            Log::warning('[自动开通] 客户通知派发失败', [
                'order_id' => (int) $order->id,
                'service_id' => (int) $service->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function ensureLocalService(Order $order): Service
    {
        if ($order->service) {
            return $order->service;
        }

        $hostname = $this->resolveProvisionHostname($order);
        $instanceName = ServiceHostname::resolveInstanceNameFromProduct(
            $order->product,
            array_merge((array) ($order->config_snapshot ?? []), [
                'hostname' => $hostname,
            ]),
            trim((string) ($order->display_product_name ?? '')),
            trim((string) ($order->product_spec_snapshot ?? $order->display_product_name ?? $order->product?->name ?? ''))
        );

        // 开通基数用目录价（应付价 + 券减免 + 会员折扣减免）：折后价进入续费定价会被再次打折，逐轮复利衰减。
        // 账单为资金真源：优先从关联账单还原目录价，无账单的存量订单按同口径从订单快照列还原。
        $catalogAmount = $order->invoice instanceof Invoice
            ? Money::catalogAmountOf($order->invoice)
            : Money::add($order->amount, $order->discount, $order->member_discount_amount);

        $service = Service::create([
            'user_id' => $order->user_id,
            'product_id' => (int) ($order->product?->id ?? $order->product_id),
            'order_id' => $order->id,
            'name' => $instanceName !== '' ? $instanceName : '未命名服务',
            'domain' => $hostname,
            'billing_cycle' => (string) $order->billing_cycle,
            'amount' => $catalogAmount,
            // 开通时快照标准续费周期价格，默认按购买时价格续费。
            'locked_pricing' => Service::buildDefaultRenewPricing(
                is_array($order->product?->pricing ?? null) ? $order->product->pricing : [],
                (string) $order->billing_cycle,
                $catalogAmount
            ),
            'status' => ServiceStatus::PENDING,
            'auto_renew' => 0,
            'provision_data' => [
                'requested_config' => $this->sanitizeRequestedConfig(
                    array_merge((array) ($order->config_snapshot ?? []), [
                        'hostname' => $hostname,
                    ])
                ),
                'created_from_order' => $order->order_no,
            ],
        ]);

        $order->forceFill([
            'service_id' => $service->id,
            'service_snapshot' => $this->buildServiceSnapshot($service),
        ])->save();

        $this->applyRenewCouponBinding($service, $order->invoice instanceof Invoice ? $order->invoice : null);

        // 服务记录首次创建（概览计数/列表随之变化），失效该用户概览缓存。
        $this->forgetServiceOverviewCache((int) $order->user_id);

        return $service;
    }

    /**
     * 新购使用「持续优惠」（discount_scope=recurring）券时，把优惠参数快照绑定到服务：
     * 后续每次续费由 ServiceRenewService 按快照自动折算（对齐营销口径「续费按优惠价持续，
     * 不恢复原价」）。快照驱动——券后续停用/删除不影响已绑定服务；非 recurring 券不绑定
     * （首单券/续费券为一次性权利，已有 user_coupon 状态机管理）。
     */
    private function applyRenewCouponBinding(Service $service, ?Invoice $invoice): void
    {
        if (! $invoice instanceof Invoice || ! SchemaMetadataCache::hasColumn('services', 'renew_coupon_id')) {
            return;
        }

        if ((int) ($invoice->coupon_id ?? 0) <= 0) {
            return;
        }

        $couponSnapshot = (array) ($invoice->coupon_snapshot ?? []);
        if ((string) ($couponSnapshot['discount_scope'] ?? '') !== 'recurring') {
            return;
        }

        $service->forceFill([
            'renew_coupon_id' => (int) $invoice->coupon_id,
            'renew_coupon_snapshot' => array_filter([
                'coupon_id' => (int) $invoice->coupon_id,
                'coupon_code' => (string) ($couponSnapshot['code'] ?? ''),
                'coupon_name' => (string) ($couponSnapshot['name'] ?? ''),
                'discount_type' => (string) ($couponSnapshot['discount_type'] ?? ''),
                'discount_value' => (float) ($couponSnapshot['discount_value'] ?? 0),
                'max_discount_amount' => $couponSnapshot['max_discount_amount'] ?? null,
                'discount_label' => (string) ($couponSnapshot['discount_label'] ?? ''),
                'bound_invoice_no' => (string) ($invoice->invoice_no ?? ''),
                'bound_at' => now()->format('Y-m-d H:i:s'),
            ], fn ($value) => $value !== '' && $value !== null),
        ])->save();
    }

    private function isAdminManualOrder(Order $order): bool
    {
        $snapshot = (array) $order->config_snapshot;

        return filter_var($snapshot['admin_manual'] ?? false, FILTER_VALIDATE_BOOL);
    }

    private function markPending(Order $order, Service $service, ?string $reason = null): void
    {
        $service->forceFill([
            'status' => ServiceStatus::PENDING,
            'suspended_reason' => $reason,
        ])->save();

        $order->forceFill([
            'service_id' => $service->id,
            'service_snapshot' => $this->buildServiceSnapshot($service),
        ])->save();

        // 服务被置为待开通，概览的状态计数随之变化，失效该用户概览缓存。
        $this->forgetServiceOverviewCache((int) $order->user_id);
    }

    /**
     * 失效用户端「服务概览」缓存。
     *
     * 服务概览按 userId 维度缓存 120s，任何会改变该用户服务集合/状态/到期/名称的写入
     * 都必须在写入提交后清除对应缓存，否则用户会读到过期概览（V4-02）。
     * 使用 app() 延迟解析：本服务在测试中经 new 直接实例化，避免新增构造依赖。
     */
    private function forgetServiceOverviewCache(int $userId): void
    {
        if ($userId > 0) {
            app(ServiceOverviewService::class)->forgetGroupedOverviewCache($userId);
        }
    }

    /**
     * 抢占实例级开通租约，返回本次持有的令牌；返回 null 表示租约被占用。
     *
     * 用单条 UPDATE 做原子 CAS（无租约或租约已过期才可抢），避免并发执行者
     * 各自「读—判断—写入」同时通过。抢到后在同一语句里累加开通尝试次数。
     * 存量库没有租约列时返回空串，表示本次运行不启用租约（保持旧行为）。
     */
    private function acquireProvisionLease(Service $service): ?string
    {
        if (! $this->supportsProvisionLease()) {
            return '';
        }

        $serviceId = (int) $service->id;
        if ($serviceId <= 0) {
            return null;
        }

        $token = bin2hex(random_bytes(24));
        $now = now()->format('Y-m-d H:i:s');

        $affected = DB::table('services')
            ->where('id', $serviceId)
            ->where(function ($query) use ($now): void {
                $query->whereNull('provision_lease_until')
                    ->orWhere('provision_lease_until', '<=', $now);
            })
            ->update([
                'provision_lease_token' => $token,
                'provision_lease_until' => now()->addSeconds(self::PROVISION_LEASE_SECONDS)->format('Y-m-d H:i:s'),
                'provision_attempts' => DB::raw('provision_attempts + 1'),
            ]);

        return $affected > 0 ? $token : null;
    }

    /**
     * 续期租约，把到期时间推到上游窗口结束（购物车锁同口径）。
     *
     * 初始 180 秒只够抢占与短暂提交；通用驱动的清购物车→加购→checkout→fund→查实例
     * 是串行 HTTP，最坏情况远超 180 秒。不续期的话，旧执行者还在 checkout 时租约
     * 已过期，第三个执行者可以接管并对同一实例发起第二笔下单。续期只在令牌仍归
     * 自己时生效，被接管后返回 false，由调用方立刻放弃本次提交。
     */
    private function renewProvisionLease(Service $service, string $leaseToken): bool
    {
        if (! $this->supportsProvisionLease()) {
            return true;
        }

        if ($leaseToken === '' || (int) $service->id <= 0) {
            return false;
        }

        $affected = DB::table('services')
            ->where('id', (int) $service->id)
            ->where('provision_lease_token', $leaseToken)
            ->update([
                'provision_lease_until' => now()->addSeconds($this->supplierCartLockTtl())->format('Y-m-d H:i:s'),
            ]);

        return $affected > 0;
    }

    /**
     * 释放租约：只清理仍属于本次执行的令牌，绝不覆盖接管者的租约。
     */
    private function releaseProvisionLease(Service $service, string $leaseToken): void
    {
        if (! $this->supportsProvisionLease() || $leaseToken === '' || (int) $service->id <= 0) {
            return;
        }

        DB::table('services')
            ->where('id', (int) $service->id)
            ->where('provision_lease_token', $leaseToken)
            ->update([
                'provision_lease_token' => null,
                'provision_lease_until' => null,
            ]);
    }

    /**
     * 回写前自证租约仍归本次执行所有。
     *
     * 上游 checkout 可能超过租约时长，过期后允许他人接管；旧执行者若继续写入，
     * 会把接管者已开通的结果覆盖回 PENDING 或旧配置。无租约列时恒为 true。
     */
    private function provisionLeaseHeld(Service $service, string $leaseToken): bool
    {
        if (! $this->supportsProvisionLease() || $leaseToken === '') {
            return true;
        }

        $serviceId = (int) $service->id;
        if ($serviceId <= 0) {
            return false;
        }

        $current = DB::table('services')->where('id', $serviceId)->value('provision_lease_token');

        return is_string($current) && hash_equals($leaseToken, $current);
    }

    /**
     * 租约列是否就绪（存量库迁移前保持旧行为）。
     */
    private function supportsProvisionLease(): bool
    {
        return SchemaMetadataCache::hasColumn('services', 'provision_lease_token');
    }

    private function shouldAutoProvision(Product $product, ?Service $service = null): bool
    {
        $this->ensureProductBinding($product);

        if ((int) $product->auto_setup !== 1) {
            return false;
        }

        if ($this->resolveProductSupplierId($product, $service) <= 0) {
            return false;
        }

        if ($this->resolveUpstreamProductId($product, $service) <= 0) {
            return false;
        }

        // 驱动类判定同样要读冻结快照：换绑到不支持开通的供应商后，
        // 在途订单的重试必须继续用首次冻结的 provider_key，否则会在
        // 「是否可自动开通」这一步就被实时绑定拦下，快照根本没机会生效。
        return $this->providerResolver->resolveByRawKey($this->resolveProviderKeyForProduct($product, $service))
            ->supports(ProvidesProvisioning::class);
    }

    private function resolveProviderKeyForProduct(?Product $product, ?Service $service = null): string
    {
        // 在途订单的驱动类必须跟端点一起冻结：换绑后 provider_key 变了，
        // 实时解析会拿旧供应商端点配新供应商驱动，两个上游的口径会串。
        $snapshot = $this->provisionCredentialSnapshot($service);
        $frozenProviderKey = $snapshot !== null ? trim((string) ($snapshot['provider_key'] ?? '')) : '';
        if ($frozenProviderKey !== '') {
            return $frozenProviderKey;
        }

        if ($product instanceof Product) {
            $this->ensureProductBinding($product);
            $resolved = $this->providerResolver->resolveForProduct($product);
            if ($resolved->key() !== null && trim($resolved->key()) !== '') {
                return (string) $resolved->key();
            }
        }

        return '';
    }

    private function provisionViaUpstream(Order $order, ?Service $service = null): array
    {
        $product = $order->product;
        $resolvedService = $service instanceof Service ? $service : ($order->service instanceof Service ? $order->service : null);
        $supplier = $this->resolveProductSupplier($product, $resolvedService);

        if (! $supplier instanceof Supplier) {
            throw new BusinessException('供应商信息不存在，无法自动开通');
        }

        $provisioning = $this->resolveProvisioningCapability($product, $resolvedService);
        if (method_exists($provisioning, 'provisionOrder')) {
            $this->resolveProvisionHostname($order, $resolvedService);
            $cartLockKey = $this->supplierCartLockKey($supplier);

            try {
                return Cache::lock($cartLockKey, $this->supplierCartLockTtl())->block(10, function () use ($order, $supplier, $provisioning, $resolvedService) {
                    // 锁内基于 DB 最新状态复查幂等：并发"队列履约 + 管理员手动重试"时，
                    // 另一路径可能已 checkout 并提交 checkpoint（upstream_invoice_id/upstream_host_id 已落库）。
                    // 必须用 fresh 数据做幂等回查，避免基于旧内存值重复走购物车流程造成上游二次开通。
                    $freshService = $resolvedService?->fresh();
                    if ($freshService instanceof Service) {
                        $this->assertNoUnresolvedUpstreamProvisionInvoice($freshService);
                    }

                    return $provisioning->provisionOrder($order, $supplier, $freshService ?? $resolvedService ?? $order->service);
                });
            } catch (UpstreamStockShortageException $exception) {
                // 上游加购/结算是唯一真实库存闸门：被「库存不足」拒绝说明本地快照
                // （prodetail.qty 展示口径）已经虚高，立即校准归零并写售罄覆盖，
                // 防止后续用户继续按陈旧快照下单付款后再次卡开通。异常原样上抛，
                // 重试与 requires_refund 链路保持不变。
                $this->productSyncService()->markUpstreamStockShortage((int) $order->product_id);

                throw $exception;
            }
        }

        // 幂等键回查：如果 service 已有 upstream_host_id，先查上游确认 host 存在且 Active，
        // 直接返回而不重新走购物车流程，避免重复开通。
        $existingService = $service ?? $order->service;
        $existingHostId = $existingService instanceof Service ? $this->resolveReusableServiceUpstreamServiceId($existingService) : null;
        if ($existingService instanceof Service && $existingHostId !== null) {
            $existingHostValue = $this->upstreamServiceIdPayloadValue($existingHostId);
            try {
                $jwt = $provisioning->login($supplier);
                $detailResponse = $provisioning->get($supplier, "/v1/hosts/{$existingHostId}", $jwt);
                if (($detailResponse['status'] ?? 0) === 200) {
                    $detailPayload = $this->extractPayload($detailResponse);
                    $hostDetail = is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];
                    Log::info('[幂等回查] 上游 host 已存在，跳过重复开通', [
                        'order_id' => $order->id,
                        'upstream_host_id' => $existingHostId,
                    ]);

                    $existingProvisionData = $this->serviceProvisionData($existingService);

                    return [
                        'requested_host' => (string) ($existingProvisionData['requested_host'] ?? ''),
                        'upstream_invoice_id' => (int) ($existingProvisionData['upstream_invoice_id'] ?? 0),
                        'upstream_host_ids' => $existingProvisionData['upstream_host_ids'] ?? [$existingHostValue],
                        'upstream_host_id' => $existingHostValue,
                        'host_detail' => $hostDetail,
                    ];
                }
            } catch (\Throwable $e) {
                // W4-#3：本地已持有上游实例标识而回查失败（404/鉴权过期/网络异常等）时，
                // 无法确认上游是否已经开通成功；继续走清空购物车→加购→checkout 会重复开通
                // 并二次扣供应商余额。此处阻断本次开通并转人工队列——异常由
                // submitUpstreamProvision 落 provision_error（待处理标记），不再静默放行。
                Log::warning('[幂等回查] 上游 host 查询失败，已阻断本次开通并转人工核实', [
                    'order_id' => $order->id,
                    'service_id' => (int) $existingService->id,
                    'upstream_host_id' => $existingHostId,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);

                throw new BusinessException(sprintf(
                    '上游实例标识 %s 回查失败，已暂停自动开通，请人工核实上游实例状态后再重试',
                    (string) $existingHostValue
                ));
            }

            // 非 200 响应同样无法确认上游实例状态，与回查异常同口径阻断，防止重复开通
            Log::warning('[幂等回查] 上游 host 查询返回非 200，已阻断本次开通并转人工核实', [
                'order_id' => $order->id,
                'service_id' => (int) $existingService->id,
                'upstream_host_id' => $existingHostId,
                'response_status' => (int) ($detailResponse['status'] ?? 0),
            ]);

            throw new BusinessException(sprintf(
                '上游实例标识 %s 回查未确认（非 200 响应），已暂停自动开通，请人工核实上游实例状态后再重试',
                (string) $existingHostValue
            ));
        }

        $cartLockKey = $this->supplierCartLockKey($supplier);

        $startedAt = microtime(true);

        return Cache::lock($cartLockKey, $this->supplierCartLockTtl())->block(10, function () use ($order, $product, $supplier, $startedAt, $provisioning, $service, $resolvedService) {
            $latency = [
                'cart_lock_wait_ms' => 0,
                'login_ms' => 0,
                'clear_cart_before_ms' => 0,
                'add_cart_ms' => 0,
                'get_cart_ms' => 0,
                'checkout_ms' => 0,
                'fund_invoice_ms' => 0,
                'find_host_ids_ms' => 0,
                'host_detail_ms' => 0,
                'clear_cart_after_ms' => 0,
            ];
            $result = 'failed';
            $requestedHost = '';
            $invoiceId = 0;
            $hostId = 0;
            // checkout 前失败的异常会携带尚未赋值的 $hostIds 进入部分成功持久化，需预定义
            $hostIds = [];
            $errorMessage = '';
            $errorClass = '';
            $jwt = null;

            $latency['cart_lock_wait_ms'] = $this->elapsedMilliseconds($startedAt);

            try {
                $stepStartedAt = microtime(true);
                $jwt = $provisioning->login($supplier);
                $latency['login_ms'] = $this->elapsedMilliseconds($stepStartedAt);

                $stepStartedAt = microtime(true);
                $provisioning->request($supplier, 'DELETE', '/v1/cart/clear', [], $jwt);
                $latency['clear_cart_before_ms'] = $this->elapsedMilliseconds($stepStartedAt);

                $payload = $this->buildUpstreamCartPayload($order, $resolvedService);
                $requestedHost = (string) ($payload['host'] ?? '');

                $stepStartedAt = microtime(true);
                $addCartResponse = $this->addProductToUpstreamCart($provisioning, $supplier, $jwt, $payload);
                $latency['add_cart_ms'] = $this->elapsedMilliseconds($stepStartedAt);
                $this->assertUpstreamSuccess($addCartResponse, [200], '加入上游购物车', $this->resolveProviderKeyForProduct($product, $resolvedService));

                $stepStartedAt = microtime(true);
                $cartResponse = $provisioning->get($supplier, '/v1/cart', $jwt);
                $latency['get_cart_ms'] = $this->elapsedMilliseconds($stepStartedAt);
                $this->assertUpstreamSuccess($cartResponse, [200], '读取上游购物车', $this->resolveProviderKeyForProduct($product, $resolvedService));
                $cartPayload = $this->extractPayload($cartResponse);
                $gateway = $this->resolveGateway($cartPayload);

                $stepStartedAt = microtime(true);
                $checkoutResponse = $provisioning->post($supplier, '/v1/cart/checkout', [
                    'payment' => $gateway,
                    'position' => [0],
                ], $jwt);
                $latency['checkout_ms'] = $this->elapsedMilliseconds($stepStartedAt);
                $this->assertUpstreamSuccess($checkoutResponse, [200, 1001], '上游购物车结算', $this->resolveProviderKeyForProduct($product, $resolvedService));

                $checkoutPayload = $this->extractPayload($checkoutResponse);
                $invoiceId = $this->extractInvoiceId($checkoutResponse, $checkoutPayload);
                $hostIds = $this->extractHostIds($checkoutResponse, $checkoutPayload);

                if ($hostIds === [] && $invoiceId > 0 && ! $this->isCompletedCheckoutResponse($checkoutResponse)) {
                    $stepStartedAt = microtime(true);
                    $fundResponse = $provisioning->post($supplier, "/v1/invoices/{$invoiceId}/fund", [], $jwt);
                    $latency['fund_invoice_ms'] = $this->elapsedMilliseconds($stepStartedAt);
                    $this->assertUpstreamSuccess($fundResponse, [200, 1001], '使用供应商余额支付上游账单', $this->resolveProviderKeyForProduct($product, $resolvedService));
                    $fundPayload = $this->extractPayload($fundResponse);
                    $hostIds = $this->extractHostIds($fundResponse, $fundPayload);

                    if ($hostIds === []) {
                        $message = trim((string) ($fundResponse['msg'] ?? ''));
                        throw new BusinessException($message !== ''
                            ? "上游账单 {$invoiceId} 支付未完成：{$message}"
                            : "上游账单 {$invoiceId} 未支付完成，请检查供应商余额是否充足");
                    }
                }

                if ($hostIds === []) {
                    $stepStartedAt = microtime(true);
                    $hostIds = $this->findUpstreamHostIdsByName($provisioning, $supplier, $jwt, (string) $payload['host']);
                    $latency['find_host_ids_ms'] = $this->elapsedMilliseconds($stepStartedAt);
                }

                if ($hostIds === []) {
                    throw new BusinessException('上游已接受订单，但未返回已开通的产品 ID');
                }

                $hostId = (int) $hostIds[0];

                $stepStartedAt = microtime(true);
                $detailResponse = $provisioning->get($supplier, "/v1/hosts/{$hostId}", $jwt);
                $latency['host_detail_ms'] = $this->elapsedMilliseconds($stepStartedAt);
                $this->assertUpstreamSuccess($detailResponse, [200], '读取上游产品详情', $this->resolveProviderKeyForProduct($product, $resolvedService));
                $detailPayload = $this->extractPayload($detailResponse);
                $hostDetail = is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];

                $result = 'success';

                return [
                    'requested_host' => (string) $payload['host'],
                    'upstream_invoice_id' => $invoiceId,
                    'upstream_host_ids' => $hostIds,
                    'upstream_host_id' => $hostId,
                    'host_detail' => $hostDetail,
                ];
            } catch (\Throwable $exception) {
                $errorMessage = $exception->getMessage();
                $errorClass = $exception::class;

                // D3A-03：checkout/fund 之后失败属于"部分成功"——上游已建账单、可能已扣供应商
                // 余额并创建实例，但标识此前仅存于闭包内存。立即把已获得的上游账单/实例标识
                // 持久化到 provision_data，让重试守卫与幂等回查能看到，防止重复下单二次扣款。
                $this->persistPartialUpstreamProvisionIdentifiers($service, $invoiceId, $hostIds, (int) $order->id);

                throw $exception;
            } finally {
                $clearStartedAt = microtime(true);

                try {
                    if (is_string($jwt) && trim($jwt) !== '') {
                        $provisioning->request($supplier, 'DELETE', '/v1/cart/clear', [], $jwt);
                    }
                } catch (\Throwable $exception) {
                    Log::warning('[自动开通] 清理供应商购物车失败', [
                        'supplier_id' => $supplier->id,
                        'order_id' => $order->id,
                        'message' => $exception->getMessage(),
                    ]);
                } finally {
                    $latency['clear_cart_after_ms'] = $this->elapsedMilliseconds($clearStartedAt);
                }

                $logContext = array_merge($latency, [
                    'result' => $result,
                    'order_id' => (int) $order->id,
                    'order_no' => (string) $order->order_no,
                    'supplier_id' => (int) $supplier->id,
                    'upstream_product_id' => $this->resolveUpstreamProductId($product, $resolvedService),
                    'requested_host' => $requestedHost,
                    'upstream_invoice_id' => $invoiceId,
                    'upstream_host_id' => $hostId,
                    'duration_ms' => $this->elapsedMilliseconds($startedAt),
                ]);

                if ($result === 'success') {
                    Log::info('[购买链路] 上游开通请求耗时', $logContext);
                } else {
                    $logContext['message'] = $errorMessage;
                    $logContext['exception'] = $errorClass;
                    Log::warning('[购买链路] 上游开通请求耗时', $logContext);
                }
            }
        });
    }

    /**
     * D3A-03：内置驱动开通"部分成功"时持久化上游账单/实例标识。
     *
     * checkout/fund 已在上游产生账单（并扣供应商余额）、可能已创建实例，但此前这些标识仅存于
     * 闭包内存，失败路径只写 provision_error，导致重试守卫因 upstream_invoice_id=0 放行而
     * 重复下单。此处把已获得的标识立即落到 provision_data：
     * - 有账单无实例（invoice_id>0 且 host_ids 为空）：assertNoUnresolvedUpstreamProvisionInvoice
     *   会拦截重新下单，转人工核实上游账单；
     * - 已取得实例标识：重试走幂等回查（resolveReusableServiceUpstreamServiceId 的 provision_data
     *   兜底口径），上游已存在则直接复用，不存在则阻断转人工。
     *
     * 直接写在传入的 Service 实例上，保证 submitUpstreamProvision 失败路径的
     * provision_data 合并（serviceProvisionData）能看到这些标识，不会被旧内存值覆盖。
     */
    private function persistPartialUpstreamProvisionIdentifiers(?Service $service, int $invoiceId, array $hostIds, int $orderId): void
    {
        if (! $service instanceof Service || ($invoiceId <= 0 && $hostIds === [])) {
            return;
        }

        try {
            $latest = $service->fresh() ?? $service;
            $provisionData = $this->serviceProvisionData($latest);
            $payload = [
                'last_provision_attempt_at' => now()->format('Y-m-d H:i:s'),
            ];

            if ($invoiceId > 0 && (int) ($provisionData['upstream_invoice_id'] ?? 0) <= 0) {
                $payload['upstream_invoice_id'] = $invoiceId;
            }

            if ($hostIds !== []) {
                $existingHostIds = is_array($provisionData['upstream_host_ids'] ?? null)
                    ? array_map(intval(...), (array) $provisionData['upstream_host_ids'])
                    : [];
                $payload['upstream_host_ids'] = array_values(array_unique(array_merge($existingHostIds, array_map(intval(...), $hostIds))));

                if ((int) ($provisionData['upstream_host_id'] ?? 0) <= 0) {
                    $payload['upstream_host_id'] = (int) $hostIds[0];
                }
            }

            $service->forceFill([
                'provision_data' => array_merge($provisionData, $payload),
            ])->save();

            Log::warning('[自动开通] 上游开通部分成功，已落库上游账单/实例标识，重试需先按标识回查幂等', [
                'order_id' => $orderId,
                'service_id' => (int) $service->id,
                'upstream_invoice_id' => $invoiceId,
                'upstream_host_ids' => $hostIds,
            ]);
        } catch (\Throwable $persistException) {
            // 落库失败时保留原始开通异常向上抛，仅记录风险日志供人工核实
            Log::error('[自动开通] 上游开通部分成功的标识落库失败，存在重复下单风险，请人工核实上游账单', [
                'order_id' => $orderId,
                'upstream_invoice_id' => $invoiceId,
                'upstream_host_ids' => $hostIds,
                'message' => $persistException->getMessage(),
                'exception' => $persistException::class,
            ]);
        }
    }

    private function buildUpstreamCartPayload(Order $order, ?Service $service = null): array
    {
        $product = $order->product;
        $configSnapshot = array_merge(
            (array) (($product->purchase_requires ?? [])['upstream_default_config'] ?? []),
            (array) ($order->config_snapshot ?? [])
        );
        // 调用方传入的 service 才是本次履约上下文；直接读 order->service 会在
        // 「显式传入 service 但订单关系未加载」时丢掉 credential_snapshot，回落到实时绑定。
        $hostname = $this->resolveProvisionHostname($order, $service ?? $order->service);
        $password = trim((string) ($configSnapshot['password'] ?? ''));

        if ($password === '') {
            $password = $this->generateProvisionPassword();
        }

        $configOptionMap = $this->buildUpstreamConfigOptionMap($product, $configSnapshot);

        return [
            'product_id' => $this->resolveUpstreamProductId($product, $service),
            'billingcycle' => (string) $order->billing_cycle,
            'qty' => 1,
            'host' => $hostname,
            'password' => $password,
            'configoption' => $configOptionMap,
        ];
    }

    private function buildUpstreamConfigOptionMap(Product $product, array $configSnapshot): array
    {
        $result = [];

        foreach ((array) ($product->config_options ?? []) as $item) {
            $optionId = $this->resolveOptionId((array) $item);
            $field = $this->parseField($item);
            $type = (int) ($item['option_type'] ?? -1);

            if ($optionId <= 0 || $field === '') {
                continue;
            }

            if (in_array($type, self::RANGE_TYPES, true)) {
                $value = $configSnapshot[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $result[$optionId] = (int) $value;

                continue;
            }

            if ($field === 'os' && ! empty($configSnapshot['os_sub_id'])) {
                $result[$optionId] = (int) $configSnapshot['os_sub_id'];

                continue;
            }

            $selected = $configSnapshot[$field] ?? null;
            if ($selected === null || $selected === '') {
                continue;
            }

            $subId = $this->resolveSubId($item, $selected);
            if ($subId !== null) {
                $result[$optionId] = $subId;
            }
        }

        return $result;
    }

    private function resolveOptionId(array $item): int
    {
        foreach ([
            $item['id'] ?? null,
            $item['config_id'] ?? null,
            $item['extra']['id'] ?? null,
            $item['extra']['config_id'] ?? null,
        ] as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        foreach ([(array) ($item['sub'] ?? []), (array) ($item['extra']['sub'] ?? [])] as $subList) {
            foreach ($subList as $sub) {
                $configId = $sub['config_id'] ?? $sub['configid'] ?? null;
                if (is_numeric($configId) && (int) $configId > 0) {
                    return (int) $configId;
                }
            }
        }

        return 0;
    }

    private function resolveSubId(array $item, mixed $selected): ?int
    {
        $selected = trim((string) $selected);
        if ($selected === '') {
            return null;
        }

        foreach ((array) ($item['sub'] ?? []) as $sub) {
            $subId = (int) ($sub['id'] ?? 0);
            $optionNameFirst = trim((string) ($sub['option_name_first'] ?? ''));
            $optionName = trim((string) ($sub['option_name'] ?? ''));

            if ($selected === (string) $subId || $selected === $optionNameFirst || $selected === $optionName) {
                return $subId > 0 ? $subId : null;
            }
        }

        return is_numeric($selected) ? (int) $selected : null;
    }

    private function parseField(array $item): string
    {
        $field = trim((string) ($item['field'] ?? ''));
        if ($field !== '') {
            return $field;
        }

        $type = (int) ($item['option_type'] ?? -1);
        if (isset(self::TYPE_FIELD_MAP[$type])) {
            return self::TYPE_FIELD_MAP[$type];
        }

        $source = (string) ($item['option_name'] ?? $item['spec_key'] ?? '');
        $parts = explode('|', $source);

        return trim((string) ($parts[0] ?? ''));
    }

    private function resolveGateway(array $cartPayload): string
    {
        $gateway = trim((string) ($cartPayload['default_gateway'] ?? ''));
        if ($gateway !== '') {
            return $gateway;
        }

        $gatewayList = $cartPayload['gateway_list'] ?? [];
        if (is_array($gatewayList)) {
            foreach ($gatewayList as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        throw new BusinessException('上游未配置可用支付网关，无法完成自动开通');
    }

    private function extractPayload(array $response): array
    {
        return is_array($response['data'] ?? null) ? $response['data'] : $response;
    }

    private function extractInvoiceId(array $response, array $payload): int
    {
        return (int) ($payload['invoiceid'] ?? $response['invoiceid'] ?? 0);
    }

    private function extractHostIds(array $response, array $payload): array
    {
        $hostIds = $payload['hostid'] ?? $response['hostid'] ?? [];

        if (! is_array($hostIds)) {
            $hostIds = [$hostIds];
        }

        return array_values(array_filter(array_map(
            fn ($value) => is_numeric($value) && (int) $value > 0 ? (int) $value : null,
            $hostIds
        )));
    }

    private function assertUpstreamSuccess(array $response, array $allowedStatuses, string $action, string $providerKey = ''): void
    {
        $hasStatus = array_key_exists('status', $response)
            || array_key_exists('code', $response)
            || array_key_exists('status_code', $response);

        if (! $hasStatus) {
            return;
        }

        $status = $this->extractResponseStatus($response);
        if (in_array($status, $allowedStatuses, true)) {
            return;
        }

        $message = trim((string) ($response['msg'] ?? $response['message'] ?? ''));
        Log::warning('[上游开通] 返回失败', [
            'action' => $action,
            'status' => $status,
            'message' => $message,
        ]);

        throw new BusinessException(app(ProviderErrorMapper::class)->toUserMessage($providerKey, $action, $message));
    }

    private function addProductToUpstreamCart(object $provisioning, Supplier $supplier, string $jwt, array $payload): array
    {
        $requestPayload = $payload;
        if (($requestPayload['configoption'] ?? []) === []) {
            $requestPayload['configoption'] = (object) [];
        }

        return $provisioning->request(
            $supplier,
            'POST',
            '/v1/cart/products',
            (string) json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $jwt,
            [
                'Content-Type: application/json',
                'Accept: application/json',
            ]
        );
    }

    private function isCompletedCheckoutResponse(array $response): bool
    {
        $status = $this->extractResponseStatus($response);

        return $status === 1001;
    }

    private function extractResponseStatus(array $response): int
    {
        return (int) ($response['status'] ?? $response['code'] ?? $response['status_code'] ?? 0);
    }

    private function findUpstreamHostIdsByName(object $provisioning, Supplier $supplier, string $jwt, string $hostname): array
    {
        $response = $provisioning->get($supplier, '/v1/hosts', $jwt, [
            'page' => 1,
            'limit' => 20,
            'keywords' => $hostname,
            'sort' => 'DESC',
        ]);

        $payload = $this->extractPayload($response);
        $hosts = is_array($payload['host'] ?? null) ? $payload['host'] : [];

        return array_values(array_filter(array_map(function ($host) use ($hostname) {
            if (! is_array($host)) {
                return null;
            }

            $domain = trim((string) ($host['domain'] ?? ''));
            if ($domain !== '' && $domain !== $hostname) {
                return null;
            }

            $id = (int) ($host['id'] ?? 0);

            return $id > 0 ? $id : null;
        }, $hosts)));
    }

    private function resolveServiceStatusFromUpstream(array $hostDetail): int
    {
        return ServiceStatus::fromUpstreamDomainStatus($hostDetail['domainstatus'] ?? '');
    }

    private function resolveServiceExpiry(array $hostDetail, Order $order): ?Carbon
    {
        $nextDueDate = $hostDetail['nextduedate'] ?? null;
        if (is_numeric($nextDueDate) && (int) $nextDueDate > 0) {
            // 上游时间戳按应用时区解析，避免 UTC 服务器上偏移 8 小时
            return Carbon::createFromTimestamp((int) $nextDueDate, config('app.timezone'));
        }

        // 到期推进统一走 BillingCycle::advance（夹月末不溢出）；未知周期交由调用方兜底
        return BillingCycle::advance(now(), (string) $order->billing_cycle);
    }

    private function sanitizeRequestedConfig(array $config): array
    {
        if (isset($config['password'])) {
            $config['password'] = '***';
        }

        return $config;
    }

    private function prepareServiceForProvisionRetry(Order $order, Service $service): void
    {
        // 重试沿用凭据快照：主机名规则也要按冻结的那个上游去取，否则改绑后会按新规则改名。
        $hostname = $this->resolveProvisionHostname($order, $service);
        $provisionData = $this->serviceProvisionData($service, includeSecrets: true);

        foreach ([
            'upstream_status',
            'upstream_product_name',
            'dedicated_ip',
            'assigned_ips',
            'host_config_option',
            'os',
            'connection_secret',
            'connection_cached_at',
            'last_provisioned_at',
            'last_provision_attempt_at',
            'last_synced_at',
            'provision_error',
            'runtime_status',
            'runtime_description',
            'nat_remote_address',
            'nat_remote_host',
            'nat_remote_port',
            'nat_remote_checked_at',
            'manual_remark',
            'manually_provisioned',
            'manual_provisioned_at',
            'manual_provisioned_by_admin_id',
            'manual_provisioned_by_admin_name',
        ] as $key) {
            unset($provisionData[$key]);
        }

        $provisionData['requested_config'] = $this->sanitizeRequestedConfig(
            array_merge((array) ($order->config_snapshot ?? []), [
                'hostname' => $hostname,
            ])
        );

        $service->forceFill([
            'status' => ServiceStatus::PENDING,
            'domain' => $hostname !== '' ? $hostname : $service->domain,
            'suspended_reason' => null,
            'provision_data' => $provisionData,
        ])->save();
    }

    private function generateProvisionPassword(): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';

        for ($i = 0; $i < 13; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return substr($password.'Aa1', 0, 16);
    }

    private function encryptConnectionCache(array $connection): string
    {
        return Crypt::encryptString((string) json_encode([
            'hostname' => trim((string) ($connection['hostname'] ?? '')),
            'username' => trim((string) ($connection['username'] ?? '')),
            'password' => (string) ($connection['password'] ?? ''),
            'port' => (int) (($connection['port'] ?? 0) ?: 0),
            'internal_ip' => trim((string) ($connection['internal_ip'] ?? '')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function resolveProvisionHostname(Order $order, ?Service $service = null): string
    {
        $configSnapshot = (array) ($order->config_snapshot ?? []);
        $hostnameConfig = $this->settingService->getProvisionHostnameConfig();
        $productHostnameRule = ProductProvisionHostname::fromPurchaseRequires(
            is_array($order->product?->purchase_requires ?? null) ? $order->product->purchase_requires : []
        );
        $upstreamHostnameRule = $this->resolveUpstreamProvisionHostnameRule($order, $service);
        $snapshotHostname = $this->settingService->normalizeHostname((string) ($configSnapshot['hostname'] ?? ''));
        $hostnameMode = (string) ($productHostnameRule['mode'] ?? ProductProvisionHostname::MODE_SYSTEM);
        $enforceSystemRule = (bool) ($hostnameConfig['enforce'] ?? false);
        $hostname = $enforceSystemRule
            ? $this->generateProvisionHostname(
                $order,
                ProductProvisionHostname::buildGenerationRule($hostnameConfig, [], $upstreamHostnameRule)
            )
            : match ($hostnameMode) {
                ProductProvisionHostname::MODE_FIXED => $this->settingService->normalizeHostname(
                    (string) ($productHostnameRule['value'] ?? ''),
                    true
                ),
                ProductProvisionHostname::MODE_PREFIX => $this->generateProvisionHostname(
                    $order,
                    ProductProvisionHostname::buildGenerationRule($hostnameConfig, $productHostnameRule, $upstreamHostnameRule)
                ),
                default => $upstreamHostnameRule !== []
                    ? $this->generateProvisionHostname(
                        $order,
                        ProductProvisionHostname::buildGenerationRule($hostnameConfig, [], $upstreamHostnameRule)
                    )
                    : $snapshotHostname,
            };

        if ($hostname === '' && $upstreamHostnameRule !== []) {
            $hostname = $this->generateProvisionHostname(
                $order,
                ProductProvisionHostname::buildGenerationRule($hostnameConfig, [], $upstreamHostnameRule)
            );
        }

        if ($hostname === '') {
            $hostname = $this->generateProvisionHostname(
                $order,
                ProductProvisionHostname::buildGenerationRule($hostnameConfig)
            );
        }

        if (($configSnapshot['hostname'] ?? '') !== $hostname) {
            $configSnapshot['hostname'] = $hostname;
            $order->forceFill([
                'config_snapshot' => $configSnapshot,
            ])->save();
        }

        return $hostname;
    }

    private function resolveUpstreamProvisionHostnameRule(Order $order, ?Service $service = null): array
    {
        $product = $order->product;
        // 与提交同源：在途订单读冻结快照，避免改绑后用新上游的规则生成旧上游的主机名。
        $supplier = $product instanceof Product ? $this->resolveProductSupplier($product, $service) : null;

        if (! $product instanceof Product || ! $supplier instanceof Supplier) {
            return [];
        }

        if (! $this->shouldAutoProvision($product, $service)) {
            return [];
        }

        $supplierProductId = $this->resolveUpstreamProductId($product, $service);
        if ($supplierProductId <= 0) {
            return [];
        }

        $cacheKey = sprintf('provision:upstream_hostname_rule:%d:%d', (int) $supplier->id, $supplierProductId);
        $cachedRule = Cache::get($cacheKey);
        if (is_array($cachedRule) && $cachedRule !== []) {
            return $cachedRule;
        }

        $rule = (function () use ($product, $supplier, $supplierProductId, $service) {
            try {
                $provisioning = $this->resolveProvisioningCapability($product, $service);
                if (method_exists($provisioning, 'getProductProvisionConfig')) {
                    $response = $provisioning->getProductProvisionConfig($supplier, $supplierProductId);

                    return $this->extractUpstreamProvisionHostnameRule($response, $supplierProductId);
                }

                $jwt = $provisioning->login($supplier);
                $response = $provisioning->get($supplier, '/v1/productsconfig', $jwt, [
                    'product_id' => $supplierProductId,
                ]);

                return $this->extractUpstreamProvisionHostnameRule($response, $supplierProductId);
            } catch (\Throwable $exception) {
                Log::warning('[自动开通] 读取上游商品主机名规则失败，将回退本地主机名规则', [
                    'supplier_id' => (int) $supplier->id,
                    'upstream_product_id' => $supplierProductId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);

                return [];
            }
        })();

        if ($rule === []) {
            Cache::forget($cacheKey);

            return [];
        }

        Cache::put($cacheKey, $rule, now()->addMinutes(30));

        return $rule;
    }

    private function resolveProvisioningCapability(?Product $product, ?Service $service = null): object
    {
        throw_if(! $product instanceof Product, new BusinessException('商品信息不存在，无法解析供应商接口', 42200));

        // 驱动类同样属于「本单履约凭据」：换绑 provider_key 后实时解析会拿新驱动打旧端点，
        // 上游协议差异会让在途重试变成不可解释的报错，所以优先按冻结快照选驱动。
        $snapshot = $this->provisionCredentialSnapshot($service);
        $frozenProviderKey = $snapshot !== null ? trim((string) ($snapshot['provider_key'] ?? '')) : '';
        if ($frozenProviderKey !== '') {
            return $this->providerResolver
                ->resolveByRawKey($frozenProviderKey)
                ->require(ProvidesProvisioning::class, '当前商品未接入可自动开通的供应商接口');
        }

        return $this->providerResolver
            ->resolveForProduct($product)
            ->require(ProvidesProvisioning::class, '当前商品未接入可自动开通的供应商接口');
    }

    private function extractUpstreamProvisionHostnameRule(array $response, int $productId): array
    {
        $product = $this->extractUpstreamProductConfigData($response, $productId);
        if (! is_array($product)) {
            return [];
        }

        return $this->normalizeUpstreamProvisionHostnameRule($product);
    }

    private function extractUpstreamProductConfigData(array $response, int $productId): ?array
    {
        $payload = is_array($response['data'] ?? null) ? $response['data'] : $response;

        foreach ([
            $payload['products'] ?? null,
            $payload['product'] ?? null,
            $payload['items'] ?? null,
        ] as $candidate) {
            $matchedProduct = $this->matchUpstreamProductCandidate($candidate, $productId);
            if ($matchedProduct !== null) {
                return $matchedProduct;
            }
        }

        if ((int) ($payload['id'] ?? 0) === $productId) {
            return $payload;
        }

        foreach ((array) ($payload['first_group'] ?? []) as $firstGroup) {
            foreach ((array) ($firstGroup['group'] ?? []) as $group) {
                $matchedProduct = $this->matchUpstreamProductCandidate(
                    $group['products'] ?? $group['product'] ?? null,
                    $productId
                );

                if ($matchedProduct !== null) {
                    return $matchedProduct;
                }
            }
        }

        return null;
    }

    private function matchUpstreamProductCandidate(mixed $candidate, int $productId): ?array
    {
        if (! is_array($candidate)) {
            return null;
        }

        if ((int) ($candidate['id'] ?? 0) === $productId) {
            return $candidate;
        }

        foreach ($candidate as $item) {
            if (is_array($item) && (int) ($item['id'] ?? 0) === $productId) {
                return $item;
            }
        }

        return null;
    }

    private function normalizeUpstreamProvisionHostnameRule(array $product): array
    {
        $hostRule = $this->normalizeUpstreamHostRule($product['host'] ?? null);
        $hostRuleRule = is_array($hostRule['rule'] ?? null) ? $hostRule['rule'] : [];
        $productRule = is_array($product['rule'] ?? null) ? $product['rule'] : [];
        $effectiveRule = $hostRuleRule !== [] ? $hostRuleRule : $productRule;
        $show = (int) ($hostRule['show'] ?? $product['show'] ?? 1);
        $sampleHostname = $this->settingService->normalizeHostname((string) (
            $hostRule['host']
            ?? $hostRule['hostname']
            ?? $hostRuleRule['host']
            ?? $hostRuleRule['hostname']
            ?? ($hostRule === [] && is_string($product['host'] ?? null) ? $product['host'] : '')
        ), true);
        $prefix = $this->settingService->sanitizeHostnamePrefix((string) (
            $hostRule['prefix']
            ?? $hostRuleRule['prefix']
            ?? $product['prefix']
            ?? ''
        ));
        $length = $this->resolveUpstreamProvisionHostnameLength($hostRule, $effectiveRule, $product, $sampleHostname, $prefix);

        if ($prefix === '' && preg_match('/^[a-zA-Z]+/', $sampleHostname, $matches) === 1) {
            $prefix = $this->settingService->sanitizeHostnamePrefix((string) ($matches[0] ?? ''));
        }

        $pool = '';
        if ($this->resolveUpstreamProvisionHostnameFlag(
            $hostRule['num'] ?? null,
            $hostRuleRule['num'] ?? null,
            $productRule['num'] ?? null,
            $product['num'] ?? null
        ) === 1) {
            $pool .= '0123456789';
        }
        if ($this->resolveUpstreamProvisionHostnameFlag(
            $hostRule['upper'] ?? null,
            $hostRuleRule['upper'] ?? null,
            $productRule['upper'] ?? null,
            $product['upper'] ?? null
        ) === 1) {
            $pool .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        if ($this->resolveUpstreamProvisionHostnameFlag(
            $hostRule['lower'] ?? null,
            $hostRuleRule['lower'] ?? null,
            $productRule['lower'] ?? null,
            $product['lower'] ?? null
        ) === 1) {
            $pool .= 'abcdefghijklmnopqrstuvwxyz';
        }

        if ($pool === '' && $sampleHostname !== '') {
            if (preg_match('/[0-9]/', $sampleHostname) === 1) {
                $pool .= '0123456789';
            }
            if (preg_match('/[A-Z]/', $sampleHostname) === 1) {
                $pool .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            }
            if (preg_match('/[a-z]/', $sampleHostname) === 1) {
                $pool .= 'abcdefghijklmnopqrstuvwxyz';
            }
        }

        if ($show !== 1 && $prefix === '' && $sampleHostname === '') {
            return [];
        }

        $length = max($length, mb_strlen($prefix));
        if ($length <= 0) {
            $length = max(mb_strlen($sampleHostname), 12);
        }

        return [
            'prefix' => $prefix,
            'length' => max(4, min(63, $length)),
            'pool' => $pool !== '' ? $pool : '0123456789',
        ];
    }

    private function normalizeUpstreamHostRule(mixed $host): array
    {
        if (is_array($host)) {
            return $host;
        }

        if (! is_string($host)) {
            return [];
        }

        $decoded = json_decode(trim($host), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveUpstreamProvisionHostnameLength(
        array $hostRule,
        array $hostRuleRule,
        array $product,
        string $sampleHostname,
        string $prefix
    ): int {
        $length = $this->resolveUpstreamProvisionHostnameNumericValue([
            $hostRule['len_num'] ?? null,
            $hostRule['length'] ?? null,
            $product['len_num'] ?? null,
            $product['length'] ?? null,
        ]);

        $ruleLength = $this->resolveUpstreamProvisionHostnameNumericValue([
            $hostRuleRule['len_num'] ?? null,
            $hostRuleRule['length'] ?? null,
        ]);

        if ($ruleLength > 0) {
            $length = max($length, $ruleLength + mb_strlen($prefix));
        }

        if ($sampleHostname !== '') {
            $length = max($length, mb_strlen($sampleHostname));
        }

        if ($length <= 0) {
            $length = max(mb_strlen($prefix), 12);
        }

        return $length;
    }

    private function resolveUpstreamProvisionHostnameFlag(mixed ...$candidates): int
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            return (int) $candidate;
        }

        return 0;
    }

    private function resolveUpstreamProvisionHostnameNumericValue(array $candidates): int
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '' || ! is_numeric($candidate)) {
                continue;
            }

            return (int) $candidate;
        }

        return 0;
    }

    private function generateProvisionHostname(Order $order, array $config): string
    {
        $prefix = (string) ($config['prefix'] ?? 'srv');
        $length = max((int) ($config['length'] ?? 12), mb_strlen($prefix));
        $pool = (string) ($config['pool'] ?? '0123456789');
        $randomLength = max($length - mb_strlen($prefix), 0);

        if ($randomLength === 0) {
            return $prefix;
        }

        return $prefix.$this->buildProvisionHostnameSuffix(
            "{$order->order_no}|{$order->id}|{$order->user_id}",
            $pool,
            $randomLength
        );
    }

    private function buildProvisionHostnameSuffix(string $seed, string $pool, int $length): string
    {
        $poolLength = strlen($pool);
        if ($poolLength <= 0 || $length <= 0) {
            return '';
        }

        $result = '';
        $cursor = 0;

        while (strlen($result) < $length) {
            $hash = hash('sha256', $seed.'|'.$cursor);
            $hashLength = strlen($hash);

            for ($index = 0; $index < $hashLength && strlen($result) < $length; $index += 2) {
                $segment = substr($hash, $index, 2);
                $offset = hexdec($segment) % $poolLength;
                $result .= $pool[$offset];
            }

            $cursor++;
        }

        return substr($result, 0, $length);
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function resolveProductSupplierId(Product $product, ?Service $service = null): int
    {
        // 履约上下文存在冻结快照时必须用快照里的供应商：后台改绑供应商后，
        // 在途订单的回写与审计仍要落在真正下单的那一家，否则账单会挂到新供应商名下。
        $snapshot = $this->provisionCredentialSnapshot($service);
        if ($snapshot !== null && (int) ($snapshot['supplier_id'] ?? 0) > 0) {
            return (int) $snapshot['supplier_id'];
        }

        $this->ensureProductBinding($product);

        $supplierId = (int) (($this->pluginBindingResolver()->supplierIdForProduct($product) ?? 0) ?: 0);
        if ($supplierId > 0) {
            return $supplierId;
        }

        return 0;
    }

    /**
     * 解析开通所用的供应商实例。
     *
     * 存在冻结快照时以快照的 supplier_id / provider_key / upstream_base_url 为准：
     * 后台改绑供应商或改端点后，在途订单的重试仍要打回原上游，否则会对着新供应商
     * 重复下单或直接查不到原账单。密钥不进快照（轮换频繁），仍从当前绑定实时取。
     */
    private function resolveProductSupplier(Product $product, ?Service $service = null): ?Supplier
    {
        $snapshot = $this->provisionCredentialSnapshot($service);
        if ($snapshot !== null) {
            $frozenSupplier = Supplier::query()->find((int) ($snapshot['supplier_id'] ?? 0));
            if ($frozenSupplier instanceof Supplier) {
                $frozenSupplier = $this->pluginBindingResolver()->supplierWithRuntimeCredentials($frozenSupplier);

                $frozenProviderKey = trim((string) ($snapshot['provider_key'] ?? ''));
                if ($frozenProviderKey !== '') {
                    $frozenSupplier->setAttribute('provider_key', $frozenProviderKey);
                }

                $frozenBaseUrl = rtrim(trim((string) ($snapshot['upstream_base_url'] ?? '')), '/');
                if ($frozenBaseUrl !== '') {
                    $frozenSupplier->setAttribute('api_url', $frozenBaseUrl);
                }

                return $frozenSupplier;
            }

            // 快照指向的供应商已被物理删除时退回实时解析，避免在途订单彻底卡死；
            // 这里的回退是「快照失效」的信号，必须留日志：否则在途订单会静默改用当前绑定
            // 的供应商下单，账单挂到错误的上游而无人知晓。
            Log::warning('[自动开通] 冻结快照指向的供应商已不存在，回退实时绑定解析', [
                'service_id' => (int) $service->id,
                'frozen_supplier_id' => (int) ($snapshot['supplier_id'] ?? 0),
            ]);
        }

        return $this->resolveLiveProductSupplier($product);
    }

    private function resolveLiveProductSupplier(Product $product): ?Supplier
    {
        $this->ensureProductBinding($product);

        $supplier = $this->pluginBindingResolver()->supplierForProduct($product);
        if ($supplier instanceof Supplier) {
            return $this->pluginBindingResolver()->supplierWithRuntimeCredentials($supplier);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function provisionCredentialSnapshot(?Service $service): ?array
    {
        if (! $service instanceof Service) {
            return null;
        }

        $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
        $snapshot = $provisionData['credential_snapshot'] ?? null;
        if (! is_array($snapshot) || (int) ($snapshot['supplier_id'] ?? 0) <= 0) {
            return null;
        }

        return $snapshot;
    }

    /**
     * 首次提交上游前冻结端点与标识，之后的重试沿用同一份快照。
     * 不冻结密钥：密钥轮换是常态，冻结会引入「密钥过期」这一新的故障面。
     */
    private function freezeProvisionCredentialSnapshot(Order $order, Service $service): void
    {
        $product = $order->product;
        if (! $product instanceof Product) {
            return;
        }

        // 只改 legacy provision_data：投影键由绑定表派生，不该被写回快照。
        $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
        $existing = $provisionData['credential_snapshot'] ?? null;
        $existing = is_array($existing) ? $existing : [];

        // 存量快照（升级前冻结的）只有 supplier_id 等标识、没有 upstream_product_id：
        // 此时要补全缺失键而不是整段覆盖，否则会把已冻结的旧端点改写成当前绑定的新端点，
        // 在途订单的重试就打到了错误的上游。
        if ((int) ($existing['supplier_id'] ?? 0) > 0 && (int) ($existing['upstream_product_id'] ?? 0) > 0) {
            return;
        }

        $supplier = $this->resolveLiveProductSupplier($product);
        if (! $supplier instanceof Supplier) {
            // 解析不到供应商时不在快照里留半截数据：后续代码会按原口径抛错，人工修好绑定后重试仍能正常冻结。
            return;
        }

        $frozenSnapshot = $existing;
        $frozenSnapshot['supplier_id'] = (int) ($existing['supplier_id'] ?? 0) > 0 ? (int) $existing['supplier_id'] : (int) $supplier->id;
        $frozenSnapshot['provider_key'] = trim((string) ($existing['provider_key'] ?? '')) !== ''
            ? trim((string) $existing['provider_key'])
            : trim((string) ($supplier->provider_key ?? ''));
        $frozenSnapshot['upstream_base_url'] = rtrim(trim((string) ($existing['upstream_base_url'] ?? '')), '/') !== ''
            ? rtrim(trim((string) $existing['upstream_base_url']), '/')
            : rtrim(trim((string) ($supplier->api_url ?? '')), '/');
        $frozenSnapshot['upstream_product_id'] = (int) ($existing['upstream_product_id'] ?? 0) > 0
            ? (int) $existing['upstream_product_id']
            : $this->resolveUpstreamProductId($product);
        $frozenSnapshot['frozen_at'] = (string) ($existing['frozen_at'] ?? '') !== ''
            ? (string) $existing['frozen_at']
            : now()->format('Y-m-d H:i:s');

        $provisionData['credential_snapshot'] = $frozenSnapshot;

        $service->forceFill(['provision_data' => $provisionData])->save();
    }

    private function resolveUpstreamProductId(Product $product, ?Service $service = null): int
    {
        // 上游商品号是真正下单的 product_id，也必须随快照冻结：
        // 换绑商品后重试若读实时绑定，会拿旧端点配新商品号，上游直接下错单。
        $snapshot = $this->provisionCredentialSnapshot($service);
        if ($snapshot !== null && (int) ($snapshot['upstream_product_id'] ?? 0) > 0) {
            return (int) $snapshot['upstream_product_id'];
        }

        $this->ensureProductBinding($product);

        $upstreamProductId = $this->pluginBindingResolver()->upstreamProductIdForProduct($product);
        if ($upstreamProductId !== null) {
            return (int) $upstreamProductId;
        }

        return 0;
    }

    private function resolveServiceUpstreamServiceId(Service $service): ?string
    {
        $upstreamServiceId = $this->nonBlank($this->pluginBindingResolver()->upstreamServiceIdForService($service));
        if ($upstreamServiceId !== null) {
            return $upstreamServiceId;
        }

        return null;
    }

    private function resolveReusableServiceUpstreamServiceId(Service $service): ?string
    {
        $bindingHostId = $this->nonBlank($this->pluginBindingResolver()->upstreamServiceIdForService($service));
        if ($bindingHostId !== null) {
            return $bindingHostId;
        }

        // D3A-03：与 assertNoUnresolvedUpstreamProvisionInvoice 保持同一口径——
        // 绑定缺失时回退 provision_data['upstream_host_id']（部分成功时已持久化的上游标识），
        // 保证这类服务在重试时先按标识回查上游幂等，而不是跳过回查直接重走购物车造成重复开通。
        $provisionData = $this->serviceProvisionData($service);

        return $this->nonBlank($provisionData['upstream_host_id'] ?? null);
    }

    private function upstreamServiceIdPayloadValue(string $upstreamServiceId): int|string
    {
        return ctype_digit($upstreamServiceId) ? (int) $upstreamServiceId : $upstreamServiceId;
    }

    private function nonBlank(mixed $value): ?string
    {
        $normalized = trim((string) ($value ?? ''));

        return $normalized !== '' ? $normalized : null;
    }

    private function assertNoUnresolvedUpstreamProvisionInvoice(Service $service): void
    {
        $provisionData = $this->serviceProvisionData($service);
        $upstreamInvoiceId = (int) ($provisionData['upstream_invoice_id'] ?? 0);
        $upstreamHostId = $this->resolveServiceUpstreamServiceId($service)
            ?? $this->nonBlank($provisionData['upstream_host_id'] ?? null);

        throw_if(
            $upstreamInvoiceId > 0 && $upstreamHostId === null,
            new BusinessException('已创建上游账单但未取得实例标识，请先核实上游账单状态后再处理，禁止直接重新下单')
        );
    }

    private function supplierCartLockTtl(): int
    {
        return max(90, (int) config('queue.caiwu_worker_timeout', 1200) + 60);
    }

    /**
     * 上游购物车按登录账号共享，不能仅按本地 supplier_id 互斥。
     * 多条供应商记录复用同一上游地址和账号时，必须落到同一个分布式锁。
     */
    private function supplierCartLockKey(Supplier $supplier): string
    {
        $baseUrl = $this->normalizeUpstreamCartLockBaseUrl((string) ($supplier->api_url ?? ''));
        $accountName = strtolower(trim((string) ($supplier->api_username ?? '')));

        if ($baseUrl === '' || $accountName === '') {
            return "lock:supplier:cart:{$supplier->id}";
        }

        return 'lock:upstream:cart:'.hash('sha256', $baseUrl.'|'.$accountName);
    }

    private function normalizeUpstreamCartLockBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        if ($baseUrl === '') {
            return '';
        }

        $parts = parse_url($baseUrl);
        if ($parts === false || ! isset($parts['host'])) {
            return $baseUrl;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        return "{$scheme}://{$host}{$port}{$path}";
    }

    private function pluginBindingResolver(): PluginBindingResolver
    {
        return $this->bindingResolver ??= new PluginBindingResolver;
    }

    private function serviceBindingWriter(): ServiceUpstreamBindingWriter
    {
        return $this->serviceBindingWriter ??= app(ServiceUpstreamBindingWriter::class);
    }

    private function buildServiceSnapshot(Service $service): array
    {
        return [
            'instance_id' => (int) $service->id,
            'hostname' => (string) ($service->domain ?? ''),
        ];
    }

    private function ensureProductBinding(Product $product): void
    {
        app(UpstreamBindingWriter::class)->syncProductBinding($product);
    }

    private function serviceProvisionData(Service $service, bool $includeSecrets = false): array
    {
        $legacy = (array) ($service->provision_data ?? []);
        $projection = $this->pluginBindingResolver()->serviceProvisionProjection($service, $includeSecrets);

        return $projection === [] ? $legacy : array_replace($legacy, $projection);
    }
}
