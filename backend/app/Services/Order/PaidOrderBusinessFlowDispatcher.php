<?php

namespace App\Services\Order;

use App\Jobs\ProcessPaidOrderFulfillmentJob;
use App\Jobs\ProcessPaidOrderReferralRewardJob;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Predis\Client;

class PaidOrderBusinessFlowDispatcher
{
    private ?bool $databaseQueueReady = null;

    public function dispatchPaidInvoice(Invoice $invoice, ?string $traceId = null): void
    {
        $orderId = (int) ($invoice->order?->id ?? 0);

        if ($orderId <= 0) {
            return;
        }

        if (app()->runningInConsole()) {
            if ($this->shouldUseQueue()) {
                ProcessPaidOrderReferralRewardJob::dispatch($orderId, $traceId);
                ProcessPaidOrderFulfillmentJob::dispatch($orderId);

                return;
            }

            $this->logFallbackDispatch($orderId);
            ProcessPaidOrderReferralRewardJob::dispatchSync($orderId, $traceId);
            ProcessPaidOrderFulfillmentJob::dispatchSync($orderId);

            return;
        }

        if ($this->shouldUseQueue()) {
            ProcessPaidOrderReferralRewardJob::dispatch($orderId, $traceId);
            ProcessPaidOrderFulfillmentJob::dispatchAfterResponse($orderId);

            return;
        }

        $this->logFallbackDispatch($orderId);

        ProcessPaidOrderReferralRewardJob::dispatchAfterResponse($orderId, $traceId);
        ProcessPaidOrderFulfillmentJob::dispatchAfterResponse($orderId);
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
            $this->databaseQueueReady = $table !== '' && Schema::hasTable($table);
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
