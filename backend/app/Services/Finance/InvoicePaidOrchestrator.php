<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Constants\UserNotificationType;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Services\ClientServiceConsole\ServiceTrafficPackageService;
use App\Services\ClientServiceConsole\ServiceUpgradeService;
use App\Services\Notification\UserNotificationService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\Provisioning\ProvisionService;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\Referral\ReferralService;
use App\Support\SchemaMetadataCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 支付成功后编排（对应 zjmf processPaidInvoiceFinal 的角色）：
 * 履约分派（Order 路径走队列 / Invoice-only 同步降级）+ 推荐奖励 + 优惠券用量同步
 * + 管理员通知 + 履约 pending 标记。由网关入账主流程、余额支付与
 * InvoiceService::markPaidManually 调用；自身不依赖任何支付流服务，依赖图无环。
 *
 * 注意：InvoiceService -> 本服务的引用使用 app() 延迟解析——本服务经
 * ServiceRenewService 依赖 InvoiceService，构造器注入会形成容器环。
 */
class InvoicePaidOrchestrator
{
    public function __construct(
        private PaidOrderBusinessFlowDispatcher $paidOrderBusinessFlowDispatcher,
        private ProvisionService $provisionService,
        private ServiceRenewService $serviceRenewService,
        private ReferralService $referralService,
        private CouponService $couponService,
        private AdminOrderNotificationService $adminOrderNotificationService,
    ) {}

    public function handlePaidInvoice(Invoice $invoice, ?string $traceId = null): void
    {
        $startedAt = microtime(true);
        $invoice = $invoice->fresh(['order.product']) ?? $invoice;

        if ((int) $invoice->status !== InvoiceStatus::PAID) {
            return;
        }

        $orderId = (int) ($invoice->order?->id ?? 0);
        $invoiceType = (string) ($invoice->type ?? $invoice->order?->type ?? '');

        if ($this->shouldTrackFulfillment($invoice, $invoiceType)) {
            $invoice = $this->markFulfillmentPending($invoice, $invoiceType);
        }

        $latency = [
            'coupon_sync_schedule_ms' => 0,
            'admin_notify_schedule_ms' => 0,
            'business_flow_dispatch_ms' => 0,
        ];

        // 开通 / 履约：优先走 Order 路径（包含完整上游开通链路），降级走 Invoice-only
        $stepStartedAt = microtime(true);
        if ($orderId > 0) {
            $this->paidOrderBusinessFlowDispatcher->dispatchPaidInvoice($invoice, $traceId);
        } else {
            if ($this->provisionPaidInvoice($invoice)) {
                $this->clearFulfillmentPending($invoice);
            }

            // 升级账单不触发推荐奖励；新购/续费账单（含无订单场景）均按各自比例发放
            if ($invoiceType !== 'upgrade') {
                $this->dispatchInvoiceOnlyReferralReward($invoice, $traceId);
            }
        }
        $latency['business_flow_dispatch_ms'] = $this->elapsedMilliseconds($stepStartedAt);

        // 优惠券同步统一走 invoice 状态机，具体执行交给 coupon 队列。
        $stepStartedAt = microtime(true);
        try {
            $this->couponService->syncInvoiceCouponUsageAfterResponse($invoice);
        } catch (\Throwable $exception) {
            Log::warning('[购买链路] 支付成功后优惠券同步调度失败', [
                'invoice_id' => (int) $invoice->id,
                'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                'trace_id' => (string) ($traceId ?? ''),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
        $latency['coupon_sync_schedule_ms'] = $this->elapsedMilliseconds($stepStartedAt);

        // 管理员通知：统一走 invoice 入口
        $stepStartedAt = microtime(true);
        try {
            $this->adminOrderNotificationService->notifyInvoicePaidAfterResponse($invoice);
        } catch (\Throwable $exception) {
            Log::warning('[购买链路] 支付成功后管理员通知调度失败', [
                'invoice_id' => (int) $invoice->id,
                'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                'trace_id' => (string) ($traceId ?? ''),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
        $latency['admin_notify_schedule_ms'] = $this->elapsedMilliseconds($stepStartedAt);

        Log::info('[购买链路] 支付成功后处理耗时', array_merge($latency, [
            'invoice_id' => (int) $invoice->id,
            'invoice_no' => (string) ($invoice->invoice_no ?? ''),
            'invoice_type' => $invoiceType,
            'order_id' => $orderId,
            'trace_id' => (string) ($traceId ?? ''),
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]));
    }

    /**
     * 直接根据账单执行开通履约（无订单场景）
     */
    private function provisionPaidInvoice(Invoice $invoice): bool
    {
        if ((int) $invoice->status !== InvoiceStatus::PAID) {
            return false;
        }

        try {
            $invoiceType = (string) ($invoice->type ?? '');
            if ($invoiceType === 'renew') {
                $service = $this->serviceRenewService->processPaidRenewInvoice($invoice);

                return $this->serviceRenewService->isRenewInvoiceFulfilled($invoice, $service);
            }

            if ($invoiceType === 'upgrade') {
                $upgradeKind = (string) data_get($invoice->config_pricing_snapshot ?? [], 'meta.kind', '');
                if ($upgradeKind === 'host_upgrade') {
                    $order = app(ServiceUpgradeService::class)
                        ->ensureHostUpgradeOrderForInvoice($invoice);

                    if (! $order instanceof Order) {
                        Log::error('[支付后自动开通] 主机升降级账单缺少可恢复的履约订单', [
                            'invoice_id' => (int) $invoice->id,
                            'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                            'trace_id' => (string) ($invoice->trace_id ?? ''),
                        ]);

                        return false;
                    }

                    // 已付旧账单可能早于 Order 投影；补齐后交给同一订单队列，
                    // 由成功路径清除 fulfillment_pending，避免在回调里同步调用上游。
                    $this->paidOrderBusinessFlowDispatcher->dispatchPaidInvoice(
                        $invoice->fresh(['order.product']) ?? $invoice,
                        (string) ($invoice->trace_id ?? '')
                    );

                    return false;
                }

                app(ServiceTrafficPackageService::class)
                    ->processPaidTrafficPackageInvoice($invoice);

                return true;
            }

            $this->provisionService->processPaidInvoice($invoice);

            return false;
        } catch (\Throwable $exception) {
            Log::error('[支付后自动开通] 基于账单的开通失败', [
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no ?? '',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * 对无订单的账单触发推荐奖励（after-response / 同步兜底）
     */
    private function dispatchInvoiceOnlyReferralReward(Invoice $invoice, ?string $traceId): void
    {
        $callback = function () use ($invoice, $traceId): void {
            try {
                $this->referralService->rewardForPaidInvoice($invoice, $traceId);
            } catch (\Throwable $exception) {
                Log::error('[支付后推荐奖励] 基于账单的奖励处理失败', [
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no ?? '',
                    'trace_id' => $traceId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        };

        if (app()->runningInConsole()) {
            $callback();

            return;
        }

        app()->terminating($callback);
    }

    public function processPaidOrderReferralRewardById(int $orderId, ?string $traceId = null): void
    {
        $order = $this->loadPayableOrderForBusinessFlow($orderId);

        if (! $order) {
            return;
        }

        try {
            $this->referralService->rewardForPaidOrder($order, $traceId);
        } catch (\Throwable $exception) {
            Log::error('[支付后推荐奖励] 处理失败', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'trace_id' => $traceId,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    public function processPaidOrderFulfillmentById(int $orderId): void
    {
        if ($orderId <= 0) {
            return;
        }

        try {
            $lock = Cache::lock("lock:paid-order-fulfillment:{$orderId}", 1500);
            $acquired = $lock->get();
        } catch (\Throwable $exception) {
            // 锁后端异常时不降级无锁履约，避免并发重复调用上游；抛错走队列重试
            Log::error('[支付后自动开通] 订单履约锁不可用，终止本次履约等待重试', [
                'order_id' => $orderId,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        if (! $acquired) {
            Log::info('[支付后自动开通] 已有同订单履约正在执行，跳过重复请求', [
                'order_id' => $orderId,
            ]);

            return;
        }

        try {
            $this->fulfillPaidOrderById($orderId);
        } finally {
            $lock->release();
        }
    }

    private function fulfillPaidOrderById(int $orderId): void
    {
        $order = $this->loadPayableOrderForBusinessFlow($orderId);

        if (! $order || ! $order->invoice) {
            return;
        }

        $fulfilled = $this->provisionPaidOrder($order->invoice);
        if (! $fulfilled) {
            throw new BusinessException('支付后履约未完成，等待后续重试');
        }

        $this->clearFulfillmentPending($order->invoice);
    }

    public function processPaidInvoiceCouponSyncById(int $invoiceId): void
    {
        $invoice = Invoice::query()->find($invoiceId);

        if (! $invoice instanceof Invoice || (int) $invoice->status !== InvoiceStatus::PAID) {
            return;
        }

        $this->couponService->syncInvoiceCouponUsage($invoice);
    }

    private function provisionPaidOrder(?Invoice $invoice): bool
    {
        $order = $invoice?->order;

        if (! $order instanceof Order || (int) $invoice->status !== InvoiceStatus::PAID) {
            return true;
        }

        try {
            if ($order->type === 'renew') {
                $service = $this->serviceRenewService->processPaidRenewOrder($order);
                if (! $this->serviceRenewService->isRenewInvoiceFulfilled($invoice, $service)) {
                    return false;
                }

                $this->notifyOrderPaid($invoice, 'renew');

                return true;
            }

            if ($order->type === 'upgrade') {
                $upgradeKind = (string) data_get($order->config_pricing_snapshot ?? [], 'meta.kind', '');

                if ($upgradeKind === 'host_upgrade') {
                    $upgradeService = app(ServiceUpgradeService::class);
                    $service = $upgradeService->processPaidUpgradeOrder($order);

                    if (! $service || ! $upgradeService->isUpgradeOrderFulfilled($order, $service)) {
                        return false;
                    }
                } else {
                    app(ServiceTrafficPackageService::class)
                        ->processPaidTrafficPackageOrder($order);
                }
                $this->notifyOrderPaid($invoice, 'upgrade');

                return true;
            }

            $service = $this->provisionService->processPaidOrder($order);
            if ($service === null) {
                return true;
            }

            // 订单状态不再有开通中/已完成子状态，履约是否完成以服务是否脱离待开通为准
            if ((int) $service->status === ServiceStatus::PENDING) {
                return false;
            }

            $this->notifyOrderPaid($invoice, 'new');

            return true;
        } catch (\Throwable $exception) {
            Log::error('[支付后自动开通] 调用开通服务失败', [
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }

    private function shouldTrackFulfillment(Invoice $invoice, string $invoiceType): bool
    {
        if ($invoiceType === 'renew') {
            return true;
        }

        $order = $invoice->order;
        if ($invoiceType !== 'new' || ! $order instanceof Order) {
            return false;
        }

        $product = $order->product;

        return $product instanceof Product && (int) $product->auto_setup === 1;
    }

    private function markFulfillmentPending(Invoice $invoice, string $invoiceType): Invoice
    {
        $configSnapshot = is_array($invoice->config_snapshot ?? null) ? $invoice->config_snapshot : [];
        if (! empty($configSnapshot['fulfillment_pending']) || ! empty($configSnapshot['fulfillment_cleared_at'])) {
            return $invoice;
        }

        $configSnapshot['fulfillment_pending'] = true;
        $configSnapshot['fulfillment_type'] = $invoiceType;

        $invoice->forceFill(['config_snapshot' => $configSnapshot])->saveQuietly();

        return $invoice->fresh(['order.product']) ?? $invoice;
    }

    private function clearFulfillmentPending(Invoice $invoice): void
    {
        $freshInvoice = $invoice->fresh();
        if (! $freshInvoice instanceof Invoice) {
            return;
        }

        $configSnapshot = is_array($freshInvoice->config_snapshot ?? null)
            ? $freshInvoice->config_snapshot
            : [];

        if (empty($configSnapshot['fulfillment_pending'])) {
            return;
        }

        $configSnapshot['fulfillment_pending'] = false;
        $configSnapshot['fulfillment_cleared_at'] = now()->toDateTimeString();

        $freshInvoice->forceFill(['config_snapshot' => $configSnapshot])->saveQuietly();
    }

    /**
     * 开通成功后写一条「订购提醒」站内信。不影响主流程，异常已在服务内吞掉。
     */
    private function notifyOrderPaid(Invoice $invoice, string $orderType): void
    {
        $userId = (int) ($invoice->user_id ?? $invoice->order?->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        $productName = (string) ($invoice->order?->display_product_name ?? '您的服务');
        $title = match ($orderType) {
            'renew' => '续费成功',
            'upgrade' => '升级/加购成功',
            default => '开通成功',
        };

        $serviceId = (int) ($invoice->order?->service_id ?? $invoice->service?->id ?? 0);
        $link = $serviceId > 0 ? '/client/services/'.$serviceId : '/client/services';

        app(UserNotificationService::class)->create(
            $userId,
            UserNotificationType::ORDER_PAID,
            $title,
            "「{$productName}」已处理完成，账单号 {$invoice->invoice_no}。",
            $link,
            [
                'invoice_id' => (int) $invoice->id,
                'order_id' => (int) ($invoice->order?->id ?? 0),
                'order_type' => $orderType,
            ]
        );
    }

    private function loadPayableOrderForBusinessFlow(int $orderId): ?Order
    {
        $relations = ['invoice', 'product.supplier', 'service'];

        static $hasUserReferrals = null;
        if ($hasUserReferrals === null) {
            $hasUserReferrals = SchemaMetadataCache::hasTable('user_referrals');
        }
        if ($hasUserReferrals) {
            $relations[] = 'user.referralProfile';
        } else {
            $relations[] = 'user';
        }

        $order = Order::query()
            ->with($relations)
            ->find($orderId);

        if (! $order instanceof Order) {
            return null;
        }

        $invoice = $order->invoice;
        if (! $invoice instanceof Invoice || (int) $invoice->status !== InvoiceStatus::PAID) {
            return null;
        }

        if ((int) $order->status !== OrderStatus::PAID) {
            return null;
        }

        return $order;
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
