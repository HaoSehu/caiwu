<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Services\Finance\InvoicePaidOrchestrator;
use App\Services\Provisioning\ProvisionService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 上游开通失败孤儿单补偿。
 *
 * 孤儿单特征：service.status=PENDING（开通中）且 provision_data.provision_error 非空、
 * order.status=PAID（已付款），即"已付款未开通"且自动开通链路失败的单。
 *
 * 默认 dry-run：只扫描并告警，不自动重试。--execute 才对 retry_count 未超上限的
 * 孤儿单调用 ProvisionService::retryFailedProvision 重试。
 *
 * 安全说明：上游开通链路 provisionViaUpstream 为"清空购物车→加购→checkout→取 host_id"，
 * 缺少上游幂等键回查；若上次失败发生在 checkout 成功之后，自动重试存在重复开通风险。
 * 因此默认 dry-run + 人工 --execute，把重复开通的决策权交给管理员，由其先核实上游
 * 实际开通状态后再决定是否重试。彻底的幂等改造见审查报告 B-016 残留 P2。
 *
 * 另一段扫描「超时未履约账单」：开通履约已改为同步/响应后执行（不走队列），队列
 * 重试耗尽的 failed() 兜底在该模式下不触发；此处以 config_snapshot.fulfillment_pending
 * 停留时长判定"自动履约已放弃"，超时（默认 60 分钟，覆盖网关重发窗口）即自动打
 * requires_refund 标记。打标是幂等纯标记写入（无资金动作、无上游调用，只标记不退款），
 * 不受 dry-run 约束；重试决策仍交人工。
 */
class RetryFailedProvisionCommand extends Command
{
    protected $signature = 'provision:retry-failed
        {--execute : 实际执行重试（默认仅扫描告警）}
        {--limit=50 : 单次最多处理孤儿单数量}
        {--max-retries=3 : 重试次数上限，超过只告警不再重试}
        {--stale-minutes=60 : 已付未履约账单的等待超时（分钟），超过即打 requires_refund 标记}';

    protected $description = '扫描上游开通失败的孤儿单与超时未履约账单，按上限重试并打退款标记，默认 dry-run 告警';

    public function handle(ProvisionService $provisionService): int
    {
        $execute = (bool) $this->option('execute');
        $limit = (int) $this->option('limit');
        $maxRetries = (int) $this->option('max-retries');
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));

        $markedInvoices = $this->markStalePendingInvoices($staleMinutes);

        $orphans = $this->findOrphans($limit);

        if ($orphans === [] && $markedInvoices === 0) {
            $this->info('未发现上游开通失败孤儿单与超时未履约账单。');

            return self::SUCCESS;
        }

        if ($markedInvoices > 0) {
            $this->warn(sprintf('已为 %d 个超时未履约的已付账单打 requires_refund 标记（等待超过 %d 分钟）。', $markedInvoices, $staleMinutes));
        }

        if ($orphans === []) {
            return self::SUCCESS;
        }

        $this->warn(sprintf('发现 %d 个上游开通失败孤儿单（模式：%s）', count($orphans), $execute ? '执行重试' : 'dry-run 告警'));

        $rows = array_map(fn (Service $service): array => [
            'service_id' => $service->id,
            'order_id' => $service->order_id,
            'user_id' => $service->user_id,
            'retry_count' => (int) ($service->provision_data['retry_count'] ?? 0),
            'provision_error' => mb_substr((string) ($service->provision_data['provision_error'] ?? ''), 0, 60),
        ], $orphans);

        $this->table(['service_id', 'order_id', 'user_id', 'retry_count', 'provision_error'], $rows);

        $summary = [
            'scanned_at' => now()->toDateTimeString(),
            'mode' => $execute ? 'execute' : 'dry_run',
            'orphan_count' => count($orphans),
            'max_retries' => $maxRetries,
            'services' => $rows,
        ];
        Log::warning('[上游开通补偿] 发现上游开通失败孤儿单', $summary);

        if (! $execute) {
            $this->line('当前为 dry-run 模式，仅告警未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。');

            return self::SUCCESS;
        }

        $retried = 0;
        $succeeded = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($orphans as $service) {
            $retryCount = (int) ($service->provision_data['retry_count'] ?? 0);

            if ($retryCount >= $maxRetries) {
                $skipped++;
                $this->line(sprintf('service_id=%d 已达重试上限 %d，跳过，需人工介入。', $service->id, $maxRetries));

                continue;
            }

            $retried++;
            try {
                $order = $service->order;
                if (! $order) {
                    $failed++;
                    $this->incrementRetryCount($service, '订单缺失');

                    continue;
                }

                $provisionService->retryFailedProvision($order);
                $succeeded++;
                $this->info(sprintf('service_id=%d 重试成功。', $service->id));
            } catch (\Throwable $exception) {
                $failed++;
                $this->incrementRetryCount($service, $exception->getMessage());
                $this->error(sprintf('service_id=%d 重试失败：%s', $service->id, mb_substr($exception->getMessage(), 0, 120)));
            }
        }

        $this->info(sprintf('重试完成：尝试 %d，成功 %d，失败 %d，跳过 %d。', $retried, $succeeded, $failed, $skipped));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 扫描超时未履约的已付新购账单并打 requires_refund 标记（幂等，自动执行）。
     *
     * 停留时长以 config_snapshot.fulfillment_pending_at 为准；存量标记（无该键）
     * 回退到 invoice.updated_at 兜底。单条异常不中断整批，继续处理其余账单。
     */
    private function markStalePendingInvoices(int $staleMinutes): int
    {
        $threshold = now()->subMinutes($staleMinutes);

        try {
            $invoices = Invoice::query()
                ->where('status', InvoiceStatus::PAID)
                ->whereRaw("JSON_EXTRACT(config_snapshot, '$.fulfillment_pending') = true")
                ->whereHas('order', fn ($query) => $query->where('type', 'new'))
                ->orderBy('id')
                ->limit(200)
                ->get();
        } catch (\Throwable $exception) {
            Log::warning('[上游开通补偿] 超时未履约账单扫描失败，本轮跳过', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return 0;
        }

        $orchestrator = app(InvoicePaidOrchestrator::class);
        $marked = 0;

        foreach ($invoices as $invoice) {
            $snapshot = is_array($invoice->config_snapshot ?? null) ? $invoice->config_snapshot : [];

            // 已标记的账单直接跳过，省一次订单查询。
            if (! empty($snapshot['requires_refund'])) {
                continue;
            }

            $pendingAtRaw = $snapshot['fulfillment_pending_at'] ?? null;
            $pendingAt = $pendingAtRaw !== null
                ? Carbon::parse((string) $pendingAtRaw)
                : $invoice->updated_at;

            if ($pendingAt === null || $pendingAt->greaterThan($threshold)) {
                continue;
            }

            $order = $invoice->order;
            if (! $order instanceof Order) {
                continue;
            }

            try {
                if ($orchestrator->markOrderFulfillmentRequiresRefund((int) $order->id, 'stale_fulfillment_pending')) {
                    $marked++;
                    $this->warn(sprintf(
                        'invoice_id=%d（order_id=%d）已付未履约超过 %d 分钟，已标记 requires_refund 待人工处理。',
                        (int) $invoice->id,
                        (int) $order->id,
                        $staleMinutes,
                    ));
                }
            } catch (\Throwable $exception) {
                Log::warning('[上游开通补偿] requires_refund 标记写入失败', [
                    'invoice_id' => (int) $invoice->id,
                    'order_id' => (int) $order->id,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return $marked;
    }

    /**
     * 扫描孤儿单：service 停在 PENDING、provision_error 非空、关联 order 已付款。
     */
    private function findOrphans(int $limit): array
    {
        return Service::query()
            ->where('status', ServiceStatus::PENDING)
            ->whereNotNull('order_id')
            ->whereHas('order', function ($query): void {
                $query->where('status', OrderStatus::PAID);
            })
            ->whereNotNull(DB::raw("JSON_EXTRACT(provision_data, '$.provision_error')"))
            ->whereRaw("JSON_EXTRACT(provision_data, '$.provision_error') != ''")
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    private function incrementRetryCount(Service $service, string $errorMessage): void
    {
        $provisionData = (array) ($service->provision_data ?? []);
        $provisionData['retry_count'] = (int) ($provisionData['retry_count'] ?? 0) + 1;
        $provisionData['last_retry_at'] = now()->format('Y-m-d H:i:s');
        $provisionData['last_retry_error'] = mb_substr($errorMessage, 0, 200);

        $service->forceFill(['provision_data' => $provisionData])->save();
    }
}
