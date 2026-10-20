<?php

namespace App\Services\Order;

use App\Jobs\ProcessPaidOrderFulfillmentJob;
use App\Jobs\ProcessPaidOrderReferralRewardJob;
use App\Models\Invoice;
use App\Services\Finance\InvoicePaidOrchestrator;
use App\Support\SchemaMetadataCache;
use Illuminate\Support\Facades\Log;
use Predis\Client;

class PaidOrderBusinessFlowDispatcher
{
    private ?bool $databaseQueueReady = null;

    /**
     * 支付入账后的业务流派发（2026-10-11 用户裁决：支付完成之后进行开通，不走队列）。
     *
     * 订单履约（上游开通链）不投递 provision 队列：
     * - HTTP（网关回调/余额支付/管理端手动标记）：响应返回给调用方之后立即执行
     *   （terminate 阶段独立执行）——支付先完成、开通紧随其后，既不阻塞回调响应
     *   （规避网关超时阶梯重发的回调风暴），也没有队列排队与 worker 依赖。
     * - 控制台（自动续费、补偿命令等）：无响应可返回，dispatchSync 进程内同步执行。
     *
     * 防重不依赖队列中间件——履约入口有订单级分布式锁
     * （InvoicePaidOrchestrator::processPaidOrderFulfillmentById 的
     * lock:paid-order-fulfillment:{orderId}），并发重复请求直接跳过。
     *
     * 返佣仍按队列策略派发：队列可用时异步投递 referral 队列，队列不可用时
     * 按运行环境回退同步执行（控制台）/ 响应后执行（HTTP）。
     *
     * 异常隔离（2026-10-13 审查修复）：履约与返佣互不依赖，任何一方失败都不得
     * 吞掉另一方——控制台路径返佣先派发（履约 dispatchSync 异常会上抛）；
     * 返佣只依赖订单/账单/用户（ReferralService::rewardForPaidOrder），不依赖履约结果，
     * 因此前置派发不会改变奖励口径。
     * HTTP 路径两段业务流各自注册独立 terminate 回调并吞异常记日志
     * （Laravel Application::terminate() 对回调循环无 try/catch，裸派发时
     * 先执行者抛异常会跳过其后注册的所有回调）。
     */
    public function dispatchPaidInvoice(Invoice $invoice, ?string $traceId = null): void
    {
        $orderId = (int) ($invoice->order?->id ?? 0);

        if ($orderId <= 0) {
            return;
        }

        $shouldDispatchReferralReward = $this->shouldDispatchReferralReward($invoice);

        if (app()->runningInConsole()) {
            if ($shouldDispatchReferralReward) {
                if ($this->shouldUseQueue()) {
                    ProcessPaidOrderReferralRewardJob::dispatch($orderId, $traceId);
                } else {
                    $this->logFallbackDispatch($orderId);
                    $this->runReferralRewardSafely($orderId, $traceId);
                }
            }

            ProcessPaidOrderFulfillmentJob::dispatchSync($orderId);

            return;
        }

        // HTTP：支付响应先返回，开通在响应后立即执行（terminate，不走队列）。
        $this->terminateSafely('履约', $orderId, $traceId, function () use ($orderId): void {
            app(InvoicePaidOrchestrator::class)->processPaidOrderFulfillmentById($orderId);
        });

        if (! $shouldDispatchReferralReward) {
            return;
        }

        if ($this->shouldUseQueue()) {
            ProcessPaidOrderReferralRewardJob::dispatch($orderId, $traceId);

            return;
        }

        $this->logFallbackDispatch($orderId);
        $this->terminateSafely('返佣', $orderId, $traceId, function () use ($orderId, $traceId): void {
            app(InvoicePaidOrchestrator::class)->processPaidOrderReferralRewardById($orderId, $traceId);
        });
    }

    private function shouldDispatchReferralReward(Invoice $invoice): bool
    {
        return in_array((string) ($invoice->order?->type ?? $invoice->type ?? ''), ['new', 'renew'], true);
    }

    /**
     * 控制台队列不可用时的返佣回退：进程内同步执行但吞异常记日志——
     * 返佣失败不应阻断其后的履约 dispatchSync（返佣自身有幂等与补偿口径）。
     */
    private function runReferralRewardSafely(int $orderId, ?string $traceId): void
    {
        try {
            ProcessPaidOrderReferralRewardJob::dispatchSync($orderId, $traceId);
        } catch (\Throwable $exception) {
            Log::error('[支付后业务流] 返佣同步执行失败（不阻断履约）', [
                'order_id' => $orderId,
                'trace_id' => (string) ($traceId ?? ''),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * 在响应终止阶段独立执行一段业务流并吞异常记日志。
     *
     * Application::terminate() 对 terminating 回调循环无 try/catch：裸注册的多个
     * afterResponse 任务中先执行者抛异常，其后注册者会被整体跳过。履约与返佣
     * 互不依赖，必须各自独立执行——任一失败不得阻断另一方。
     */
    private function terminateSafely(string $label, int $orderId, ?string $traceId, callable $task): void
    {
        app()->terminating(function () use ($label, $orderId, $traceId, $task): void {
            try {
                $task();
            } catch (\Throwable $exception) {
                Log::error("[支付后业务流] {$label} 响应后执行失败（不影响其他业务流）", [
                    'order_id' => $orderId,
                    'trace_id' => (string) ($traceId ?? ''),
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    private function shouldUseQueue(): bool
    {
        $driver = (string) config('queue.default', 'sync');

        if ($driver === '' || $driver === 'sync') {
            return false;
        }

        if ($driver === 'database') {
            return $this->databaseQueueIsReady();
        }

        if ($driver === 'redis') {
            return extension_loaded('redis') || class_exists(Client::class);
        }

        return true;
    }

    private function databaseQueueIsReady(): bool
    {
        if ($this->databaseQueueReady !== null) {
            return $this->databaseQueueReady;
        }

        $table = (string) config('queue.connections.database.table', 'jobs');

        try {
            $this->databaseQueueReady = $table !== '' && SchemaMetadataCache::hasTable($table);
        } catch (\Throwable $exception) {
            Log::warning('[支付后业务流] 检查队列表失败，回退为 afterResponse', [
                'table' => $table,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            $this->databaseQueueReady = false;
        }

        return $this->databaseQueueReady;
    }

    private function logFallbackDispatch(int $orderId): void
    {
        Log::info('[支付后业务流] 队列未就绪，回退为 afterResponse/同步执行', [
            'order_id' => $orderId,
            'queue_driver' => (string) config('queue.default', 'sync'),
            'running_in_console' => app()->runningInConsole(),
        ]);
    }
}
