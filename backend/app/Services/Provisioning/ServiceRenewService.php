<?php

namespace App\Services\Provisioning;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\CouponService;
use App\Services\Finance\InvoiceService;
use App\Services\ProductCatalog\ProductDisplayNameResolver;
use App\Services\System\NotificationService;
use App\Services\System\OperationLogService;
use App\Services\System\SettingService;
use App\Services\Upstream\Contracts\ProvidesRenewal;
use App\Services\Upstream\ProviderResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceRenewService
{
    private const EXPIRED_SUSPENDED_AT_KEY = 'expired_suspended_at';

    private const CYCLE_SORT_MAP = [
        'monthly' => 1,
        'quarterly' => 2,
        'semiannually' => 3,
        'annually' => 4,
        'biennially' => 5,
        'triennially' => 6,
        'one_time' => 7,
        'onetime' => 7,
    ];

    public function __construct(
        private InvoiceService $invoiceService,
        private ProviderResolver $providerResolver,
        private CouponService $couponService,
        private OperationLogService $operationLogService,
        private SettingService $settingService,
        private NotificationService $notificationService,
    ) {}

    public function previewForUser(User $user, int $serviceId, ?string $selectedBillingCycle = null, int $userCouponId = 0): array
    {
        $service = $this->findUserService($user, $serviceId);
        $service = $this->healServiceProductMapping($service);
        $renewConfig = $this->buildRenewConfig($service);
        $effectiveProduct = $this->resolveEffectiveProduct($service) ?? $service->product;
        $productPricing = Service::extractSupportedRenewPricing(
            is_array($effectiveProduct?->pricing ?? null) ? $effectiveProduct->pricing : []
        );
        $currentCycleConfig = $service->getRenewPricingCycle((string) $service->billing_cycle, $productPricing);
        $previewAmount = (float) (
            $currentCycleConfig['effective_amount']
            ?? collect($renewConfig['cycles'])->first()['amount']
            ?? 0
        );
        $resolvedBillingCycle = trim((string) ($selectedBillingCycle ?: $renewConfig['default_cycle'] ?: $service->billing_cycle));
        $selectedCycleConfig = collect($renewConfig['cycles'])->firstWhere('billing_cycle', $resolvedBillingCycle);
        $selectedAmount = round((float) ($selectedCycleConfig['amount'] ?? $previewAmount), 2);
        $availableCoupons = $effectiveProduct instanceof Product
            ? $this->couponService->availableCouponsForCheckout((int) $user->id, $effectiveProduct, $resolvedBillingCycle, $selectedAmount, 'renew')
            : [];
        $selectedCoupon = null;
        if ($effectiveProduct instanceof Product && $userCouponId > 0) {
            try {
                $selectedCoupon = $this->couponService->previewOwnedCoupon(
                    $userCouponId,
                    (int) $user->id,
                    $effectiveProduct,
                    $resolvedBillingCycle,
                    $selectedAmount,
                    'renew'
                );
            } catch (\Throwable) {
                $selectedCoupon = null;
            }
        }

        $cycles = collect($renewConfig['cycles'])
            ->map(function (array $cycle) use ($effectiveProduct, $user, $userCouponId) {
                $amount = round((float) ($cycle['amount'] ?? 0), 2);
                $originalAmount = number_format($amount, 2, '.', '');
                $discountAmount = '0.00';
                $finalAmount = $originalAmount;

                if ($effectiveProduct instanceof Product && $userCouponId > 0) {
                    try {
                        $couponPayload = $this->couponService->previewOwnedCoupon(
                            $userCouponId,
                            (int) $user->id,
                            $effectiveProduct,
                            (string) ($cycle['billing_cycle'] ?? ''),
                            $amount,
                            'renew'
                        );

                        if (is_array($couponPayload)) {
                            $discountAmount = (string) ($couponPayload['discount_amount'] ?? '0.00');
                            $finalAmount = (string) ($couponPayload['final_amount'] ?? $originalAmount);
                        }
                    } catch (\Throwable) {
                        // 单个周期不满足优惠条件时保留原价展示
                    }
                }

                return [
                    ...$cycle,
                    'original_amount' => $originalAmount,
                    'discount_amount' => $discountAmount,
                    'amount' => $finalAmount,
                ];
            })
            ->values()
            ->all();

        return [
            'service_id' => (int) $service->id,
            'service_name' => (string) $service->name,
            'billing_cycle' => (string) $service->billing_cycle,
            'billing_cycle_label' => $this->resolveBillingCycleLabel((string) $service->billing_cycle),
            'expires_at' => $service->expires_at?->format('Y-m-d H:i:s'),
            'renew_price' => number_format($previewAmount, 2, '.', ''),
            'auto_renew' => (int) $service->auto_renew,
            'supports_upstream' => $renewConfig['supports_upstream'],
            'upstream_host_id' => $renewConfig['host_id'],
            'cycles' => $cycles,
            'default_cycle' => $resolvedBillingCycle,
            'available_coupons' => $availableCoupons,
            'selected_user_coupon_id' => (int) ($selectedCoupon['user_coupon_id'] ?? 0),
            'selected_coupon' => $selectedCoupon,
            'has_locked_pricing' => $service->usesCustomRenewPricing($productPricing),
            'has_custom_renew_pricing' => $service->usesCustomRenewPricing($productPricing),
        ];
    }

    public function createRenewInvoiceForUser(User $user, int $serviceId, string $billingCycle, int $userCouponId = 0, array $context = []): Invoice
    {
        $service = $this->findUserService($user, $serviceId);
        $service = $this->healServiceProductMapping($service);
        $renewConfig = $this->buildRenewConfig($service);
        $cycle = trim($billingCycle);
        $cycleOption = collect($renewConfig['cycles'])->firstWhere('billing_cycle', $cycle);
        $effectiveProduct = $this->resolveEffectiveProduct($service) ?? $service->product;

        throw_if(! is_array($cycleOption), new BusinessException('当前服务不支持所选续费周期'));
        throw_if(! $effectiveProduct instanceof Product, new BusinessException('服务关联商品不存在，无法创建续费账单'));

        $amount = round((float) ($cycleOption['amount'] ?? 0), 2);
        throw_if($amount <= 0, new BusinessException('当前续费周期金额无效'));

        $existingInvoice = Invoice::query()
            ->where('user_id', $user->id)
            ->where('service_id', $service->id)
            ->where('type', 'renew')
            ->where('billing_cycle', $cycle)
            ->where('user_coupon_id', $userCouponId > 0 ? $userCouponId : null)
            ->where('status', InvoiceStatus::UNPAID)
            ->latest('id')
            ->first();

        if ($existingInvoice instanceof Invoice) {
            $existingAmount = round((float) $existingInvoice->amount + (float) ($existingInvoice->discount ?? 0), 2);
            $existingDiscount = round((float) ($existingInvoice->discount ?? 0), 2);
            $expectedCouponPayload = $this->couponService->previewOwnedCoupon(
                $userCouponId > 0 ? $userCouponId : null,
                (int) $user->id,
                $effectiveProduct,
                $cycle,
                $amount,
                'renew'
            );
            $expectedDiscount = round((float) ($expectedCouponPayload['discount_amount'] ?? 0), 2);

            if ($existingAmount === $amount && $existingDiscount === $expectedDiscount && (int) $existingInvoice->product_id === (int) $effectiveProduct->id) {
                $existingInvoice->loadMissing(['product:id,product_type,product_group_id,config_options,purchase_requires', 'service']);
                $this->operationLogService->writeServiceConsoleLog($service, 'service.console.renew.invoice.create', [
                    'category' => 'renew',
                    'summary' => '获取待支付续费账单',
                    'billing_cycle' => $cycle,
                    'billing_cycle_label' => $this->resolveBillingCycleLabel($cycle),
                    'amount' => number_format(max($amount - $expectedDiscount, 0), 2, '.', ''),
                    'invoice_id' => (int) $existingInvoice->id,
                    'invoice_no' => (string) $existingInvoice->invoice_no,
                    'reused_invoice' => true,
                ], $context);

                return $existingInvoice;
            }

            app(CheckoutService::class)->cancel($existingInvoice, array_merge($context, [
                'actor_type' => (string) ($context['actor_type'] ?? 'system'),
                'actor_user_id' => (int) ($context['actor_user_id'] ?? $user->id),
                'actor_name' => (string) ($context['actor_name'] ?? ($user->display_name ?? $user->nickname ?? $user->email ?? '')),
                'reason' => 'renew_invoice_replaced',
            ]));
        }

        $invoice = DB::transaction(function () use ($user, $service, $cycle, $amount, $renewConfig, $cycleOption, $effectiveProduct, $userCouponId) {
            $displayPayload = (new ProductDisplayNameResolver)->resolveForProduct($effectiveProduct);
            $productSpecDisplay = (string) ($displayPayload['product_spec_display'] ?? '');
            $couponPayload = $this->couponService->reserveOwnedCouponForInvoice(
                $userCouponId > 0 ? $userCouponId : null,
                (int) $user->id,
                $effectiveProduct,
                $cycle,
                $amount,
                'renew'
            );
            $discountAmount = round((float) ($couponPayload['discount_amount'] ?? 0), 2);
            $payableAmount = round(max($amount - $discountAmount, 0), 2);

            $invoice = Invoice::create([
                'invoice_no' => Invoice::generateInvoiceNo(),
                'user_id' => $user->id,
                'product_id' => $effectiveProduct->id,
                'product_spec_snapshot' => $productSpecDisplay,
                'product_type_snapshot' => (string) $effectiveProduct->product_type,
                'service_id' => $service->id,
                'type' => 'renew',
                'amount' => $payableAmount,
                'discount' => $discountAmount,
                'paid_amount' => 0,
                'coupon_id' => $couponPayload['coupon_id'] ?? null,
                'user_coupon_id' => $couponPayload['user_coupon_id'] ?? null,
                'coupon_code' => $couponPayload['code'] ?? null,
                'billing_cycle' => $cycle,
                'config_snapshot' => array_filter([
                    'renew_service_id' => (int) $service->id,
                    'renew_service_name' => (string) $service->name,
                    'source_type' => (string) (($service->provision_data['source_type'] ?? '') ?: 'manual'),
                    'upstream_host_id' => $renewConfig['host_id'],
                    'supports_upstream' => $renewConfig['supports_upstream'],
                    'local_renew_amount' => number_format($amount, 2, '.', ''),
                    'upstream_amount' => (string) ($cycleOption['upstream_amount'] ?? ''),
                    'discount_amount' => number_format($discountAmount, 2, '.', ''),
                ], fn ($value) => ! in_array($value, ['', null], true)),
                'coupon_snapshot' => $couponPayload,
                'status' => InvoiceStatus::UNPAID,
                'due_date' => now()->addDays(7),
            ]);

            $this->invoiceService->syncProjection($invoice);

            return $invoice->load(['product:id,product_type,product_group_id,config_options,purchase_requires', 'service']);
        });
        $discountAmount = round((float) ($invoice->discount ?? 0), 2);
        $payableAmount = round((float) $invoice->amount, 2);

        $this->operationLogService->writeServiceConsoleLog($service, 'service.console.renew.invoice.create', [
            'category' => 'renew',
            'summary' => '创建续费账单',
            'billing_cycle' => $cycle,
            'billing_cycle_label' => $this->resolveBillingCycleLabel($cycle),
            'amount' => number_format($payableAmount, 2, '.', ''),
            'discount' => number_format($discountAmount, 2, '.', ''),
            'invoice_id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'reused_invoice' => false,
        ], $context);

        return $invoice;
    }

    public function updateAutoRenewForUser(User $user, int $serviceId, int $autoRenew, array $context = []): array
    {
        $service = $this->findUserService($user, $serviceId);
        $service = $this->healServiceProductMapping($service);
        $enabled = $autoRenew === 1 ? 1 : 0;

        if ($this->supportsUpstreamRenew($service, $this->resolveEffectiveProduct($service))) {
            $supplier = ($this->resolveEffectiveProduct($service) ?? $service->product)?->supplier;
            $hostId = $this->resolveUpstreamHostId($service);

            if ($supplier instanceof Supplier && $hostId > 0) {
                $renewal = $this->resolveRenewalCapability($service, $this->resolveEffectiveProduct($service));
                $response = $renewal->setHostAutoRenew($supplier, $hostId, $enabled);
                $this->assertUpstreamSuccess($response, [200], '同步上游自动续费状态');
            }
        }

        $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
        $provisionData['initiative_renew'] = $enabled;

        $service->forceFill([
            'auto_renew' => $enabled,
            'provision_data' => $provisionData,
        ])->save();

        $this->operationLogService->writeServiceConsoleLog($service, 'service.console.renew.auto_update', [
            'category' => 'renew',
            'summary' => $enabled === 1 ? '开启自动续费' : '关闭自动续费',
            'auto_renew' => $enabled,
            'auto_renew_label' => $enabled === 1 ? '已开启' : '已关闭',
            'host_id' => $this->resolveUpstreamHostId($service),
        ], $context);

        return [
            'service_id' => (int) $service->id,
            'auto_renew' => $enabled,
        ];
    }

    public function processPaidRenewOrder(Order $order): ?Service
    {
        $order->loadMissing(['invoice', 'service.product.supplier', 'product.supplier']);

        if ($order->invoice instanceof Invoice) {
            return $this->processPaidRenewInvoice($order->invoice);
        }

        return null;

        $service = $order->service;

        if (! $service instanceof Service) {
            return null;
        }

        if ($this->isRenewOrderAlreadyCompleted($order, $service)) {
            return $service->fresh(['product.supplier', 'order']) ?? $service;
        }

        $service = $this->healServiceProductMapping($service);
        $effectiveProduct = $this->resolveEffectiveProduct($service) ?? $service->product;

        if (! $this->supportsUpstreamRenew($service, $effectiveProduct)) {
            return $this->completeLocalRenewal($service, $order);
        }

        $supplier = $effectiveProduct?->supplier;
        $hostId = $this->resolveUpstreamHostId($service);

        if (! $supplier instanceof Supplier || $hostId <= 0) {
            return $this->completeLocalRenewal($service, $order);
        }

        try {
            $renewal = $this->resolveRenewalCapability($service, $effectiveProduct);
            $jwt = $renewal->login($supplier);
            $renewResponse = $renewal->renewHost($supplier, $hostId, (string) $order->billing_cycle);
            $this->assertUpstreamSuccess($renewResponse, [200], '提交上游续费');
            $renewPayload = $this->extractPayload($renewResponse);
            $invoiceId = (int) ($renewPayload['invoiceid'] ?? $renewResponse['invoiceid'] ?? 0);

            throw_if($invoiceId <= 0, new BusinessException('上游未返回续费账单 ID'));

            $fundResponse = $renewal->post($supplier, "/v1/invoices/{$invoiceId}/fund", [], $jwt);
            $this->assertUpstreamSuccess($fundResponse, [200, 1001], '使用供应商余额支付续费账单');

            $hostDetail = [];

            try {
                $detailResponse = $renewal->get($supplier, "/v1/hosts/{$hostId}", $jwt);
                $this->assertUpstreamSuccess($detailResponse, [200], '读取上游续费结果');
                $detailPayload = $this->extractPayload($detailResponse);
                $hostDetail = is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];
            } catch (\Throwable $detailException) {
                Log::warning('[服务续费] 上游已完成续费，但读取实例详情失败，已按本地结果回写', [
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'service_id' => $service->id,
                    'host_id' => $hostId,
                    'upstream_invoice_id' => $invoiceId,
                    'message' => $detailException->getMessage(),
                    'exception' => $detailException::class,
                ]);
            }

            $nextExpiresAt = $this->resolveRenewedExpiry($service, (string) $order->billing_cycle, $hostDetail);
            $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
            $provisionData['upstream_invoice_id'] = $invoiceId;
            $provisionData['upstream_status'] = (string) ($hostDetail['domainstatus'] ?? ($provisionData['upstream_status'] ?? ''));
            $provisionData['initiative_renew'] = (int) $service->auto_renew;
            $provisionData['last_renewed_at'] = now()->format('Y-m-d H:i:s');
            $provisionData['last_renew_billing_cycle'] = (string) $order->billing_cycle;
            $provisionData['last_renew_order_id'] = (int) $order->id;
            $provisionData['last_renew_order_no'] = (string) $order->order_no;
            $provisionData['last_renew_source'] = 'upstream';
            $provisionData['renew_error'] = null;

            return $this->finalizeRenewSuccess($service, $order, $provisionData, $nextExpiresAt);
        } catch (\Throwable $exception) {
            $message = $exception instanceof BusinessException
                ? $exception->getMessage()
                : '上游续费失败，请联系管理员处理';

            $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
            $provisionData['renew_error'] = $message;
            $provisionData['last_renew_attempt_at'] = now()->format('Y-m-d H:i:s');

            DB::transaction(function () use ($service, $order, $provisionData) {
                $service->forceFill([
                    'provision_data' => $provisionData,
                ])->save();

                $order->forceFill([
                    'service_id' => (int) $service->id,
                    'status' => OrderStatus::PROCESSING,
                ])->save();
            });

            Log::error('[服务续费] 上游续费失败', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'service_id' => $service->id,
                'host_id' => $hostId,
                'message' => $message,
                'exception' => $exception::class,
            ]);

            return $service->fresh(['product.supplier', 'order']);
        }
    }

    /**
     * 基于账单处理已支付续费（无订单场景的过渡桥接）
     */
    public function processPaidRenewInvoice(Invoice $invoice): ?Service
    {
        $invoice->loadMissing(['service.product.supplier', 'order.service.product.supplier', 'order.product.supplier']);

        $serviceId = (int) ($invoice->service_id ?? 0);
        if ($serviceId <= 0 && $invoice->order?->service) {
            $serviceId = (int) $invoice->order->service->id;
        }

        if ($serviceId <= 0) {
            Log::warning('[服务续费] 账单无关联服务，无法续费', ['invoice_id' => $invoice->id]);

            return null;
        }

        $service = Service::with(['product.supplier'])->find($serviceId);
        if (! $service instanceof Service) {
            return null;
        }

        $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
        if ((int) ($provisionData['last_renew_invoice_id'] ?? 0) === (int) $invoice->id) {
            return $service->fresh(['product.supplier']) ?? $service;
        }

        $service = $this->healServiceProductMapping($service);
        $effectiveProduct = $this->resolveEffectiveProduct($service) ?? $service->product;
        $billingCycle = (string) ($invoice->billing_cycle ?? $service->billing_cycle);

        // 尝试上游续费
        if ($this->supportsUpstreamRenew($service, $effectiveProduct)) {
            $supplier = $effectiveProduct?->supplier;
            $hostId = $this->resolveUpstreamHostId($service);

            if ($supplier instanceof Supplier && $hostId > 0) {
                try {
                    $renewal = $this->resolveRenewalCapability($service, $effectiveProduct);
                    $jwt = $renewal->login($supplier);
                    $renewResponse = $renewal->renewHost($supplier, $hostId, $billingCycle);
                    $this->assertUpstreamSuccess($renewResponse, [200], '提交上游续费');
                    $renewPayload = $this->extractPayload($renewResponse);
                    $upstreamInvoiceId = (int) ($renewPayload['invoiceid'] ?? $renewResponse['invoiceid'] ?? 0);

                    throw_if($upstreamInvoiceId <= 0, new BusinessException('上游未返回续费账单 ID'));

                    $fundResponse = $renewal->post($supplier, "/v1/invoices/{$upstreamInvoiceId}/fund", [], $jwt);
                    $this->assertUpstreamSuccess($fundResponse, [200, 1001], '使用供应商余额支付续费账单');

                    $hostDetail = [];
                    try {
                        $detailResponse = $renewal->get($supplier, "/v1/hosts/{$hostId}", $jwt);
                        $this->assertUpstreamSuccess($detailResponse, [200], '读取上游续费结果');
                        $detailPayload = $this->extractPayload($detailResponse);
                        $hostDetail = is_array($detailPayload['host'] ?? null) ? $detailPayload['host'] : [];
                    } catch (\Throwable $detailException) {
                        Log::warning('[服务续费·账单] 上游已完成续费，但读取实例详情失败，已按本地结果回写', [
                            'invoice_id' => $invoice->id,
                            'invoice_no' => $invoice->invoice_no ?? '',
                            'service_id' => $service->id,
                            'host_id' => $hostId,
                            'upstream_invoice_id' => $upstreamInvoiceId,
                            'message' => $detailException->getMessage(),
                            'exception' => $detailException::class,
                        ]);
                    }

                    $nextExpiresAt = $this->resolveRenewedExpiry($service, $billingCycle, $hostDetail);
                    $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
                    $provisionData['upstream_invoice_id'] = $upstreamInvoiceId;
                    $provisionData['upstream_status'] = (string) ($hostDetail['domainstatus'] ?? ($provisionData['upstream_status'] ?? ''));
                    $provisionData['initiative_renew'] = (int) $service->auto_renew;
                    $provisionData['last_renewed_at'] = now()->format('Y-m-d H:i:s');
                    $provisionData['last_renew_billing_cycle'] = $billingCycle;
                    $provisionData['last_renew_invoice_id'] = (int) $invoice->id;
                    $provisionData['last_renew_invoice_no'] = (string) $invoice->invoice_no;
                    $provisionData['last_renew_source'] = 'upstream';
                    $provisionData['renew_error'] = null;

                    return $this->finalizeRenewInvoiceSuccess($service, $invoice, $provisionData, $nextExpiresAt);
                } catch (\Throwable $exception) {
                    $message = $exception instanceof BusinessException
                        ? $exception->getMessage()
                        : '上游续费失败，请联系管理员处理';

                    $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
                    $provisionData['renew_error'] = $message;
                    $provisionData['last_renew_attempt_at'] = now()->format('Y-m-d H:i:s');

                    $service->forceFill(['provision_data' => $provisionData])->save();

                    Log::error('[服务续费·账单] 上游续费失败', [
                        'invoice_id' => $invoice->id,
                        'invoice_no' => $invoice->invoice_no ?? '',
                        'service_id' => $service->id,
                        'host_id' => $hostId,
                        'message' => $message,
                        'exception' => $exception::class,
                    ]);

                    return $service->fresh(['product.supplier']) ?? $service;
                }
            }
        }

        // 不支持上游续费或条件不满足时，走本地续费
        return $this->completeLocalRenewByInvoice($service, $invoice, $billingCycle);
    }

    private function completeLocalRenewByInvoice(Service $service, Invoice $invoice, string $billingCycle): Service
    {
        $nextExpiresAt = $this->resolveRenewedExpiry($service, $billingCycle);
        $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
        $provisionData['last_renewed_at'] = now()->format('Y-m-d H:i:s');
        $provisionData['last_renew_billing_cycle'] = $billingCycle;
        $provisionData['last_renew_invoice_id'] = (int) $invoice->id;
        $provisionData['last_renew_invoice_no'] = (string) $invoice->invoice_no;
        $provisionData['last_renew_source'] = 'local_invoice';
        $provisionData['renew_error'] = null;

        return $this->finalizeRenewInvoiceSuccess($service, $invoice, $provisionData, $nextExpiresAt);
    }

    private function finalizeRenewInvoiceSuccess(Service $service, Invoice $invoice, array $provisionData, ?Carbon $nextExpiresAt): Service
    {
        $previousStatus = (int) $service->status;
        $resolvedStatus = $this->resolveRenewedStatus($previousStatus);
        unset($provisionData[self::EXPIRED_SUSPENDED_AT_KEY]);

        DB::transaction(function () use ($service, $invoice, $provisionData, $nextExpiresAt, $resolvedStatus, $previousStatus) {
            $service->forceFill([
                'product_id' => (int) ($invoice->product_id ?: $service->product_id),
                'invoice_id' => (int) $invoice->id,
                'billing_cycle' => (string) ($invoice->billing_cycle ?? $service->billing_cycle),
                'amount' => (float) $invoice->amount,
                'expires_at' => $nextExpiresAt,
                'status' => $resolvedStatus,
                'provision_data' => $provisionData,
                'suspended_reason' => in_array($previousStatus, [ServiceStatus::EXPIRED, ServiceStatus::SUSPENDED], true) ? null : $service->suspended_reason,
            ])->save();
        });

        $this->sendUnsuspendNotificationIfNeeded($service, $previousStatus);

        return $service->fresh(['product.supplier']) ?? $service;
    }

    private function completeLocalRenewal(Service $service, Order $order): Service
    {
        $nextExpiresAt = $this->resolveRenewedExpiry($service, (string) $order->billing_cycle);
        $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
        $provisionData['last_renewed_at'] = now()->format('Y-m-d H:i:s');
        $provisionData['last_renew_billing_cycle'] = (string) $order->billing_cycle;
        $provisionData['last_renew_order_id'] = (int) $order->id;
        $provisionData['last_renew_order_no'] = (string) $order->order_no;
        $provisionData['last_renew_source'] = 'local';
        $provisionData['renew_error'] = null;

        return $this->finalizeRenewSuccess($service, $order, $provisionData, $nextExpiresAt);
    }

    private function buildRenewConfig(Service $service): array
    {
        $effectiveProduct = $this->resolveEffectiveProduct($service) ?? $service->product;
        $cycles = [];
        $defaultCycle = trim((string) $service->billing_cycle);
        $hostId = $this->resolveUpstreamHostId($service);
        $supportsUpstream = $this->supportsUpstreamRenew($service, $effectiveProduct);
        $localPricing = Service::extractSupportedRenewPricing(
            is_array($effectiveProduct?->pricing ?? null) ? $effectiveProduct->pricing : []
        );

        foreach (Service::SUPPORTED_RENEW_BILLING_CYCLES as $cycle => $label) {
            $cycleConfig = $service->getRenewPricingCycle($cycle, $localPricing);
            if (! is_array($cycleConfig) || empty($cycleConfig['enabled'])) {
                continue;
            }

            $localAmount = (float) ($cycleConfig['effective_amount'] ?? 0);
            if ($localAmount <= 0) {
                continue;
            }

            $cycles[] = [
                'billing_cycle' => $cycle,
                'billing_cycle_label' => $label,
                'amount' => number_format($localAmount, 2, '.', ''),
                'upstream_amount' => '',
            ];
        }

        usort($cycles, fn (array $left, array $right) => (self::CYCLE_SORT_MAP[$left['billing_cycle']] ?? 999) <=> (self::CYCLE_SORT_MAP[$right['billing_cycle']] ?? 999));

        if (! collect($cycles)->contains(fn (array $item) => $item['billing_cycle'] === $defaultCycle)) {
            $defaultCycle = (string) ($cycles[0]['billing_cycle'] ?? $defaultCycle);
        }

        return [
            'cycles' => array_values($cycles),
            'default_cycle' => $defaultCycle,
            'supports_upstream' => $supportsUpstream,
            'host_id' => $hostId,
        ];
    }

    private function resolveRenewedExpiry(Service $service, string $billingCycle, array $hostDetail = []): ?Carbon
    {
        $nextDueDate = $hostDetail['nextduedate'] ?? null;
        if (is_numeric($nextDueDate) && (int) $nextDueDate > 0) {
            return Carbon::createFromTimestamp((int) $nextDueDate);
        }

        $base = $service->expires_at instanceof Carbon && $service->expires_at->isFuture()
            ? $service->expires_at->copy()
            : now();

        return match ($billingCycle) {
            'monthly' => $base->addMonth(),
            'quarterly' => $base->addMonths(3),
            'semiannually' => $base->addMonths(6),
            'annually' => $base->addYear(),
            'biennially' => $base->addYears(2),
            'triennially' => $base->addYears(3),
            default => $base,
        };
    }

    private function resolveRenewedStatus(int $status): int
    {
        return in_array($status, [ServiceStatus::ACTIVE, ServiceStatus::PENDING, ServiceStatus::SUSPENDED, ServiceStatus::EXPIRED], true)
            ? ServiceStatus::ACTIVE
            : $status;
    }

    private function finalizeRenewSuccess(Service $service, Order $order, array $provisionData, ?Carbon $nextExpiresAt): Service
    {
        $previousStatus = (int) $service->status;
        $resolvedStatus = $this->resolveRenewedStatus($previousStatus);
        unset($provisionData[self::EXPIRED_SUSPENDED_AT_KEY]);

        DB::transaction(function () use ($service, $order, $provisionData, $nextExpiresAt, $resolvedStatus, $previousStatus) {
            $service->forceFill([
                'product_id' => (int) ($order->product_id ?: $service->product_id),
                'order_id' => (int) $order->id,
                'billing_cycle' => (string) $order->billing_cycle,
                'amount' => (float) $order->amount,
                'expires_at' => $nextExpiresAt,
                'status' => $resolvedStatus,
                'provision_data' => $provisionData,
                'suspended_reason' => in_array($previousStatus, [ServiceStatus::EXPIRED, ServiceStatus::SUSPENDED], true) ? null : $service->suspended_reason,
            ])->save();

            $order->forceFill([
                'service_id' => (int) $service->id,
                'status' => OrderStatus::COMPLETED,
            ])->save();
        });

        $this->sendUnsuspendNotificationIfNeeded($service, $previousStatus);

        return $service->fresh(['product.supplier', 'order']) ?? $service;
    }

    private function isRenewOrderAlreadyCompleted(Order $order, Service $service): bool
    {
        if ((int) $order->status === OrderStatus::COMPLETED) {
            return true;
        }

        $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];

        return (int) ($provisionData['last_renew_order_id'] ?? 0) === (int) $order->id;
    }

    private function supportsUpstreamRenew(Service $service, ?Product $product = null): bool
    {
        return $this->resolveUpstreamHostId($service) > 0
            && $this->providerResolver->resolveForService($service)->supports(ProvidesRenewal::class);
    }

    private function resolveEffectiveProduct(Service $service): ?Product
    {
        $currentProduct = $service->product;
        $provisionData = is_array($service->provision_data ?? null) ? $service->provision_data : [];
        $supplierId = (int) (($provisionData['supplier_id'] ?? ($currentProduct?->supplier_id ?? 0)) ?: 0);
        $upstreamProductId = (int) (($provisionData['upstream_product_id'] ?? $provisionData['supplier_product_id'] ?? 0) ?: 0);

        if ($supplierId <= 0 || $upstreamProductId <= 0) {
            return $currentProduct;
        }

        return Product::query()
            ->with('supplier')
            ->where('supplier_id', $supplierId)
            ->where('supplier_product_id', $upstreamProductId)
            ->where('status', 1)
            ->orderByDesc('id')
            ->first() ?: $currentProduct;
    }

    private function healServiceProductMapping(Service $service): Service
    {
        $matchedProduct = $this->resolveEffectiveProduct($service);

        if (! $matchedProduct instanceof Product || (int) $matchedProduct->id === (int) $service->product_id) {
            return $service;
        }

        $service->forceFill([
            'product_id' => (int) $matchedProduct->id,
        ])->save();

        $service->setRelation('product', $matchedProduct);

        return $service;
    }

    private function resolveUpstreamHostId(Service $service): int
    {
        return (int) ((((array) ($service->provision_data ?? []))['upstream_host_id'] ?? 0) ?: 0);
    }

    private function findUserService(User $user, int $serviceId): Service
    {
        return Service::query()
            ->with(['product.supplier', 'order'])
            ->where('user_id', $user->id)
            ->findOrFail($serviceId);
    }

    private function resolveBillingCycleLabel(string $billingCycle, string $fallback = ''): string
    {
        if ($fallback !== '') {
            return $fallback;
        }

        return [
            'monthly' => '月付',
            'quarterly' => '季付',
            'semiannually' => '半年付',
            'annually' => '年付',
            'biennially' => '两年付',
            'triennially' => '三年付',
            'one_time' => '一次性',
            'onetime' => '一次性',
        ][$billingCycle] ?? $billingCycle;
    }

    private function normalizeAmount(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function extractPayload(array $response): array
    {
        return is_array($response['data'] ?? null) ? $response['data'] : $response;
    }

    private function assertUpstreamSuccess(array $response, array $allowedStatuses, string $action): void
    {
        $status = (int) ($response['status'] ?? $response['code'] ?? $response['status_code'] ?? 200);
        if (in_array($status, $allowedStatuses, true)) {
            return;
        }

        $message = trim((string) ($response['msg'] ?? $response['message'] ?? ''));
        throw new BusinessException($message !== '' ? "{$action}失败：{$message}" : "{$action}失败");
    }

    private function resolveRenewalCapability(Service $service, ?Product $product = null): object
    {
        if ($product instanceof Product) {
            $resolved = $this->providerResolver->resolveForProduct($product);
            if ($resolved->supports(ProvidesRenewal::class)) {
                return $resolved->require(ProvidesRenewal::class, '当前服务未接入可续费的供应商接口');
            }
        }

        return $this->providerResolver
            ->resolveForService($service)
            ->require(ProvidesRenewal::class, '当前服务未接入可续费的供应商接口');
    }

    private function sendUnsuspendNotificationIfNeeded(Service $service, int $previousStatus): void
    {
        $config = $this->settingService->getAutomationConfig();
        if (! $config['expire_unsuspend_notify_enabled']) {
            return;
        }

        if (! in_array($previousStatus, [ServiceStatus::SUSPENDED, ServiceStatus::EXPIRED], true) || (int) $service->status !== ServiceStatus::ACTIVE) {
            return;
        }

        $service->loadMissing('user:id,email,nickname');
        $email = trim((string) ($service->user?->email ?? ''));
        if ($email === '') {
            return;
        }

        try {
            $this->notificationService->sendTemplateEmail($email, NotificationService::TEMPLATE_SERVICE_RESTORED, [
                'display_name' => (string) ($service->user?->display_name ?? '客户'),
                'service_name' => (string) $service->name,
                'expires_at' => $service->expires_at?->format('Y-m-d H:i:s') ?? '-',
            ]);
        } catch (\Throwable $exception) {
            Log::warning('[服务续费] 恢复通知发送失败', [
                'service_id' => $service->id,
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
