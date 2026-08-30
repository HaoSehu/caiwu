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
     * 支付入账后的履约统一走队列派发：此前 new/renew 在支付回调 HTTP 请求内
     * dispatchSync 同步执行完整上游开通链（多次串行上游调用），回调响应时长被
     * 上游绑架，网关判定超时后阶梯重发形成回调风暴。异步化后由队列消费履约，
     * 前端通过服务状态轮询呈现开通进度；队列不可用时回退 afterResponse 兜底，
     * 审计与资金入账仍在本调用内同步完成，不受影响。
     */
    public function dispatchPaidInvoice(Invoice $invoice, ?string $traceId = null): void
    {
        $orderId = (int) ($invoice->order?->id ?? 0);

        if ($orderId <= 0) {
            return;
        }

        $shouldDispatchReferralReward = $this->shouldDispatchReferralReward($invoice);

        if (app()->runningInConsole()) {
            if ($this->shouldUseQueue()) {
                if ($shouldDispatchReferralReward) {
                    ProcessPaidOrderReferralRewardJob::dispatch($orderId, $traceId);
                }

                ProcessPaidOrderFulfillmentJob::dispatch($orderId);

                return;
            }

            $this->logFallbackDispatch($orderId);
            if ($shouldDispatchReferralReward) {
                ProcessPaidOrderReferralRewardJob::dispatchSync($orderId, $traceId);
            }

            ProcessPaidOrderFulfillmentJob::dispatchSync($orderId);

            return;
        }

        if ($this->shouldUseQueue()) {
            if ($shouldDispatchReferralReward) {
                ProcessPaidOrderReferralRewardJob::dispatch($orderId, $traceId);
            }

            ProcessPaidOrderFulfillmentJob::dispatch($orderId);

            return;
        }

        $this->logFallbackDispatch($orderId);

        if ($shouldDispatchReferralReward) {
            ProcessPaidOrderReferralRewardJob::dispatchAfterResponse($orderId, $traceId);
        }

        ProcessPaidOrderFulfillmentJob::dispatchAfterResponse($orderId);
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
