<?php

namespace App\Services\Order;

use App\Jobs\ProcessPaidOrderFulfillmentJob;
use App\Jobs\ProcessPaidOrderReferralRewardJob;
use App\Models\Invoice;
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
     * - HTTP（网关回调/余额支付/管理端手动标记）：dispatchAfterResponse 在支付响应
     *   返回给调用方之后立即执行——支付先完成、开通紧随其后，既不阻塞回调响应
     *   （规避网关超时阶梯重发的回调风暴），也没有队列排队与 worker 依赖。
     * - 控制台（自动续费、补偿命令等）：无响应可返回，dispatchSync 进程内同步执行。
     *
     * 防重不依赖队列中间件——履约入口有订单级分布式锁
     * （InvoicePaidOrchestrator::processPaidOrderFulfillmentById 的
     * lock:paid-order-fulfillment:{orderId}），并发重复请求直接跳过。
     *
     * 返佣仍按队列策略派发：队列可用时异步投递 referral 队列，队列不可用时
     * 按运行环境回退 dispatchSync（控制台）/ dispatchAfterResponse（HTTP）。
     */
    public function dispatchPaidInvoice(Invoice $invoice, ?string $traceId = null): void
    {
        $orderId = (int) ($invoice->order?->id ?? 0);

        if ($orderId <= 0) {
            return;
        }

        $shouldDispatchReferralReward = $this->shouldDispatchReferralReward($invoice);

        if (app()->runningInConsole()) {
            ProcessPaidOrderFulfillmentJob::dispatchSync($orderId);

            if (! $shouldDispatchReferralReward) {
                return;
            }

            if ($this->shouldUseQueue()) {
                ProcessPaidOrderReferralRewardJob::dispatch($orderId, $traceId);

                return;
            }

            $this->logFallbackDispatch($orderId);
            ProcessPaidOrderReferralRewardJob::dispatchSync($orderId, $traceId);

            return;
        }

        // HTTP：支付响应先返回，开通在响应后立即执行（afterResponse，不走队列）。
        ProcessPaidOrderFulfillmentJob::dispatchAfterResponse($orderId);

        if (! $shouldDispatchReferralReward) {
            return;
        }

        if ($this->shouldUseQueue()) {
            ProcessPaidOrderReferralRewardJob::dispatch($orderId, $traceId);

            return;
        }

        $this->logFallbackDispatch($orderId);
        ProcessPaidOrderReferralRewardJob::dispatchAfterResponse($orderId, $traceId);
    }

    private function shouldDispatchReferralReward(Invoice $invoice): bool
    {
        return in_array((string) ($invoice->order?->type ?? $invoice->type ?? ''), ['new', 'renew'], true);
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
