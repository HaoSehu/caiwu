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
        // 但同步执行失败时必须把履约转进队列：terminate 阶段用户已经拿到「支付成功」，
        // 只记一行日志等于这单永远不会再被开通，用户付了钱拿不到服务。
        $this->terminateSafely('履约', $orderId, $traceId, function () use ($orderId): void {
            app(InvoicePaidOrchestrator::class)->processPaidOrderFulfillmentById($orderId);
        }, retryFulfillmentOnFailure: true);

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
    private function terminateSafely(string $label, int $orderId, ?string $traceId, callable $task, bool $retryFulfillmentOnFailure = false): void
    {
        app()->terminating(function () use ($label, $orderId, $traceId, $task, $retryFulfillmentOnFailure): void {
            try {
                $task();
            } catch (\Throwable $exception) {
                Log::error("[支付后业务流] {$label} 响应后执行失败（不影响其他业务流）", [
                    'order_id' => $orderId,
                    'trace_id' => (string) ($traceId ?? ''),
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);

                if ($retryFulfillmentOnFailure) {
                    $this->retryFulfillmentViaQueue($orderId, $traceId, $exception);
                }
            }
        });
    }

    /**
     * 响应后同步履约失败时的兜底：把同一订单交给队列重试。
     *
     * 队列不可用（sync 驱动 / 队列表缺失）时不派发，只保留上面那条错误日志——
     * 此时没有任何可靠的异步通道，硬派发会在请求结束后再炸一次。
     * 派发本身失败也只记日志：terminate 阶段的异常无人接管，抛出去只会污染日志。
     */
    private function retryFulfillmentViaQueue(int $orderId, ?string $traceId, \Throwable $failure): void
    {
        if (! $this->shouldUseQueue()) {
            Log::error('[支付后业务流] 履约同步失败且队列不可用，无法转入异步重试', [
                'order_id' => $orderId,
                'trace_id' => (string) ($traceId ?? ''),
                'message' => $failure->getMessage(),
            ]);

            return;
        }

        // ShouldBeUnique 的重复派发会被 PendingDispatch 静默丢弃（不抛异常），
        // 直接 dispatch 后写「已转入队列重试」会制造假证据。先探测唯一锁：
        // 已有人在队列里跑就如实记「跳过」，让日志与真实行为一致。
        if (ProcessPaidOrderFulfillmentJob::isUniqueLocked($orderId)) {
            Log::warning('[支付后业务流] 同订单履约任务已在队列中，跳过重复派发', [
                'order_id' => $orderId,
                'trace_id' => (string) ($traceId ?? ''),
                'message' => $failure->getMessage(),
            ]);

            return;
        }

        try {
            ProcessPaidOrderFulfillmentJob::dispatch($orderId);

            Log::warning('[支付后业务流] 履约同步失败，已转入队列重试', [
                'order_id' => $orderId,
                'trace_id' => (string) ($traceId ?? ''),
                'message' => $failure->getMessage(),
            ]);
        } catch (\Throwable $dispatchException) {
            Log::error('[支付后业务流] 履约同步失败且转入队列也失败，需人工介入', [
                'order_id' => $orderId,
                'trace_id' => (string) ($traceId ?? ''),
                'message' => $failure->getMessage(),
                'dispatch_message' => $dispatchException->getMessage(),
                'exception' => $dispatchException::class,
            ]);
        }
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
