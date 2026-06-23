<?php

namespace App\Services\Automation;

use App\Constants\InvoiceStatus;
use App\Constants\ServiceStatus;
use App\Models\Service;
use App\Services\Finance\PaymentService;
use App\Services\Provisioning\ServiceRenewService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AutoRenewService
{
    public function __construct(
        private ServiceRenewService $serviceRenewService,
        private PaymentService $paymentService,
    ) {}

    public function handle(int $aheadMinutes = 10): array
    {
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
            ->whereIn('status', [ServiceStatus::ACTIVE, ServiceStatus::EXPIRED])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addMinutes(max($aheadMinutes, 1)))
            ->orderBy('id')
            ->chunkById(100, function ($services) use (&$summary) {
                foreach ($services as $service) {
                    $summary['matched']++;

                    $lock = Cache::lock("lock:auto-renew:service:{$service->id}", 30);

                    $processed = $lock->get(function () use ($service, &$summary) {
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
                            $invoice = $this->serviceRenewService->createRenewInvoiceForUser(
                                $service->user,
                                (int) $service->id,
                                (string) $service->billing_cycle,
                                0,
                                $autoRenewContext
                            );

                            $invoice->loadMissing(['product.supplier', 'service']);

                            if (! $invoice) {
                                $summary['skipped']++;

                                return true;
                            }

                            if ((int) $invoice->status === InvoiceStatus::PAID) {
                                $renewedService = $this->serviceRenewService->processPaidRenewInvoice($invoice->fresh(['product.supplier', 'service']));

                                if ($this->serviceRenewService->isRenewInvoiceFulfilled($invoice->fresh(['service']), $renewedService)) {
                                    $summary['recovered']++;
                                } else {
                                    $summary['blocked']++;
                                }

                                return true;
                            }

                            if ((int) $invoice->status !== InvoiceStatus::UNPAID) {
                                $summary['skipped']++;

                                return true;
                            }

                            $payableAmount = max((float) $invoice->amount - (float) ($invoice->paid_amount ?? 0), 0);
                            $service->user->refresh();

                            if ((float) $service->user->balance < $payableAmount || $payableAmount <= 0) {
                                $summary['pending']++;

                                return true;
                            }

                            $this->paymentService->payByBalance($invoice->fresh(['product.supplier', 'service']), $service->user, $autoRenewContext);
                            $summary['paid']++;

                            return true;
                        } catch (\Throwable $exception) {
                            $summary['failed']++;

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
}
