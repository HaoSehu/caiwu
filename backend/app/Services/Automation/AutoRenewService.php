<?php

namespace App\Services\Automation;

use App\Constants\InvoiceStatus;
use App\Constants\OrderType;
use App\Constants\ServiceStatus;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Services\Finance\BalanceInvoicePaymentService;
use App\Services\Provisioning\ServiceRenewService;
use App\Support\CacheKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AutoRenewService
{
    public function __construct(
        private ServiceRenewService $serviceRenewService,
        private BalanceInvoicePaymentService $paymentService,
    ) {}

    public function handle(): array
    {
        $aheadDays = (int) config('idc.auto_renew_days_before', 3);
        // 滚动窗口：到期前 $aheadDays 天内的服务都命中（而非仅第 N 天当天），
        // 余额不足跨日充值后仍可重试；已过期转入「已暂停(reason=expired)」的服务
        // 用 expires_at < now() 收口，使「过期后仍可自动续费」可达。
        $windowEnd = now()->addDays($aheadDays)->endOfDay();

        $summary = [
            'matched' => 0,
            'paid' => 0,
            'pending' => 0,
            'failed' => 0,
            'skipped' => 0,
            'recovered' => 0,
            'blocked' => 0,
        ];

        Service::query()
            ->with(['user', 'product.supplier', 'order'])
            ->where('auto_renew', 1)
            ->where(function ($query) use ($windowEnd) {
                // 运行中：已进入续费窗口且尚未到期
                $query->where(function ($inner) use ($windowEnd) {
                    $inner->where('status', ServiceStatus::ACTIVE)
                        ->where('expires_at', '>', now())
                        ->where('expires_at', '<=', $windowEnd);
                })
                    // 到期未处理的服务现为"已暂停(reason=expired)"，过期后仍保留自动续费机会
                    ->orWhere(fn ($q) => $q->where('status', ServiceStatus::SUSPENDED)
                        ->where('suspended_reason', 'expired')
                        ->where('expires_at', '<', now()));
            })
            ->whereNotNull('expires_at')
            ->orderBy('id')
            ->chunkById(100, function ($services) use (&$summary) {
                foreach ($services as $service) {
                    $summary['matched']++;

                    $expiresAt = $service->expires_at instanceof Carbon
                        ? $service->expires_at
                        : Carbon::parse($service->expires_at);

                    // 幂等键只取「服务+到期时间」：同一到期周期内至多成功扣款一次，
                    // 失败/余额不足时 forgetRecord 允许跨日重试；不掺入日期或 aheadDays，
                    // 避免改配置后对同一到期时间重复扣款。
                    $ruleKey = 'expiry:'.$expiresAt->toDateString().':auto_renew';
                    if (! AutomationLog::recordOnce(
                        'auto_renew', 'charge', 'service', (int) $service->id, $ruleKey
                    )) {
                        $summary['skipped']++;

                        continue;
                    }

                    $lock = Cache::lock(CacheKey::lock('lock:auto-renew:service', (string) $service->id), 30);

                    $processed = $lock->get(function () use ($service, &$summary, $ruleKey, $expiresAt) {
                        if (! $service->user) {
                            $summary['skipped']++;

                            return true;
                        }

                        if (! $service->product) {
                            $summary['skipped']++;

                            return true;
                        }

                        try {
                            $traceId = 'auto_renew:service:'.$service->id.':'.now()->format('YmdHi');
                            $autoRenewContext = [
                                'auto_renew' => true,
                                'source' => 'auto_renew',
                                'operator' => 'auto_renew',
                                'actor_type' => 'system',
                                'actor_name' => '自动续费',
                                'trace_id' => $traceId,
                            ];
                            $preview = $this->serviceRenewService->previewForUser(
                                $service->user,
                                (int) $service->id,
                                (string) $service->billing_cycle,
                                0
                            );
                            $resolvedCycle = (string) ($preview['default_cycle'] ?? $service->billing_cycle);
                            $selectedCycle = collect($preview['cycles'] ?? [])->firstWhere('billing_cycle', $resolvedCycle);
                            $payableAmount = max((float) ($selectedCycle['amount'] ?? $preview['renew_price'] ?? 0), 0);
                            $service->user->refresh();

                            // 已有用户支付过且未履约完成的续费账单：等待既有账单履约，跳过本次扣款避免重复建单
                            $reusedPaidOrder = $this->serviceRenewService->findPaidUnfulfilledRenewOrder(
                                $service->user,
                                $service,
                                $resolvedCycle
                            );
                            if ($reusedPaidOrder instanceof Order) {
                                AutomationLog::markExecuted(
                                    'auto_renew',
                                    'charge',
                                    'service',
                                    (int) $service->id,
                                    $ruleKey,
                                    [
                                        'amount' => (float) ($reusedPaidOrder->invoice?->amount ?? $payableAmount),
                                        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                                        'reused_order_id' => (int) $reusedPaidOrder->id,
                                    ]
                                );
                                $summary['paid']++;

                                return true;
                            }

                            // 第二道防双扣闸：同服务同周期已存在「履约失败待人工退款」的已付续费账单时，
                            // 上游续费失败后防双扣闸门会整体释放，重入若继续建单扣款将重复收款并二次调用上游。
                            // 该账单资金结清（退款后状态变更/标记清除）前跳过自动扣款，记 blocked 供运营核对。
                            if ($this->hasBlockingRequiresRefundInvoice($service, $resolvedCycle)) {
                                $summary['blocked']++;
                                // 阻断原因单独落一条可观测记录；本次认领标记回滚，
                                // 待人工退款结清后下一个调度周期仍可自动续费。
                                AutomationLog::markExecuted(
                                    'auto_renew',
                                    'charge',
                                    'service',
                                    (int) $service->id,
                                    'blocked_requires_refund',
                                    [
                                        'billing_cycle' => $resolvedCycle,
                                        'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                                    ]
                                );
                                AutomationLog::forgetRecord('auto_renew', 'charge', 'service', (int) $service->id, $ruleKey);

                                return true;
                            }

                            if ((float) $service->user->balance < $payableAmount || $payableAmount <= 0) {
                                $summary['pending']++;
                                // 余额不足不标记 executed，下次调度可以重试
                                AutomationLog::forgetRecord('auto_renew', 'charge', 'service', (int) $service->id, $ruleKey);

                                return true;
                            }

                            $invoice = $this->serviceRenewService->createRenewInvoiceForUser(
                                $service->user,
                                (int) $service->id,
                                $resolvedCycle,
                                0,
                                $autoRenewContext
                            );

                            $this->paymentService->payByBalance(
                                $invoice->fresh(['service.product.supplier', 'order']) ?? $invoice,
                                $service->user,
                                $autoRenewContext
                            );

                            AutomationLog::markExecuted(
                                'auto_renew',
                                'charge',
                                'service',
                                (int) $service->id,
                                $ruleKey,
                                [
                                    'amount' => $payableAmount,
                                    'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                                ]
                            );
                            $summary['paid']++;

                            return true;
                        } catch (\Throwable $exception) {
                            $summary['failed']++;
                            AutomationLog::forgetRecord('auto_renew', 'charge', 'service', (int) $service->id, $ruleKey);

                            Log::error('[自动续费] 执行失败', [
                                'service_id' => $service->id,
                                'user_id' => $service->user_id,
                                'message' => $exception->getMessage(),
                                'exception' => $exception::class,
                            ]);

                            return true;
                        }
                    });

                    if (! $processed) {
                        $summary['skipped']++;
                    }
                }
            });

        return $summary;
    }

    /**
     * 同服务同周期是否已存在「已支付但履约失败、待人工退款」的续费账单。
     *
     * 续费履约失败后 ServiceRenewService 会标记 requires_refund 并放行新建账单
     * （防双扣闸门随之释放）；自动续费若仅依赖该闸门，重入时会再次扣款并二次调用上游续费。
     * 该账单资金结清前必须阻断自动扣款。
     */
    private function hasBlockingRequiresRefundInvoice(Service $service, string $billingCycle): bool
    {
        return Invoice::query()
            ->where('service_id', (int) $service->id)
            ->where('type', OrderType::RENEW)
            ->where('billing_cycle', $billingCycle)
            ->where('status', InvoiceStatus::PAID)
            ->where('requires_refund', true)
            ->exists();
    }
}
