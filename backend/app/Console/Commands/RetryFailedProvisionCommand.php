<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\ProvisionLeaseBusyException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use App\Services\Finance\InvoicePaidOrchestrator;
use App\Services\Provisioning\ProvisionService;
use App\Support\SchemaMetadataCache;
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
        {--execute : 实际执行孤儿单重试（默认仅扫描告警；账单打标始终执行）}
        {--limit=50 : 单次最多处理孤儿单数量}
        {--max-retries=3 : 重试次数上限，超过只告警不再重试}
        {--stale-minutes=60 : 已付未履约账单的等待超时（分钟），超过即打 requires_refund 标记}';

    protected $description = '扫描上游开通失败孤儿单与超时未履约账单并按上限重试；账单打标属于幂等标记写入，始终执行，孤儿单重试默认 dry-run 告警';

    public function handle(ProvisionService $provisionService): int
    {
        $execute = (bool) $this->option('execute');
        $limit = (int) $this->option('limit');
        $maxRetries = (int) $this->option('max-retries');
        $staleMinutes = max(5, (int) $this->option('stale-minutes'));

        $markedInvoices = $this->markStalePendingInvoices($staleMinutes);

        $orphans = $this->findOrphans($limit);
        // 往轮已打过标记、仍未人工处理的实例：先读再打标，避免本轮新打的标记被重复列一遍。
        // 每次运行都列出来，作为这些标记唯一的运维出口——否则 lease_exhausted_* 只是
        // 埋在 provision_data JSON 里的死数据，没有任何消费方。
        $awaitingManualReview = $this->findLeaseExhaustedAwaitingReview($limit);
        $leaseExhausted = $this->markLeaseExhaustedServices($limit);

        if ($orphans === [] && $markedInvoices === 0 && $leaseExhausted === [] && $awaitingManualReview === []) {
            $this->info('未发现上游开通失败孤儿单与超时未履约账单。');

            return self::SUCCESS;
        }

        if ($markedInvoices > 0) {
            $this->warn(sprintf('已为 %d 个超时未履约的已付账单打 requires_refund 标记（等待超过 %d 分钟）。', $markedInvoices, $staleMinutes));
        }

        if ($leaseExhausted !== []) {
            $this->warn(sprintf('已为 %d 个开通租约过期且累计尝试达上限的服务打人工核实标记，未自动重试。', count($leaseExhausted)));
        }

        if ($awaitingManualReview !== []) {
            $this->warn(sprintf('有 %d 个实例因租约耗尽待人工核实上游账单，核实前不会自动重试。', count($awaitingManualReview)));
            $this->table(
                ['service_id', 'order_id', 'user_id', 'provision_attempts', 'lease_exhausted_at', 'lease_exhausted_reason'],
                array_map(fn (Service $service): array => [
                    'service_id' => $service->id,
                    'order_id' => $service->order_id,
                    'user_id' => $service->user_id,
                    'provision_attempts' => (int) ($service->provision_attempts ?? 0),
                    'lease_exhausted_at' => (string) ($service->provision_data['lease_exhausted_at'] ?? ''),
                    'lease_exhausted_reason' => mb_substr((string) ($service->provision_data['lease_exhausted_reason'] ?? ''), 0, 60),
                ], $awaitingManualReview)
            );
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
            // 措辞必须与真实行为一致：上面两个打标方法已经落库（幂等纯标记、无资金动作、
            // 无上游调用，不受 dry-run 约束），dry-run 只拦住孤儿单的上游重试。
            $this->line('当前为 dry-run 模式：账单打标已执行，孤儿单未自动重试。核实上游实际开通状态后，可用 --execute 手动触发重试。');

            return self::SUCCESS;
        }

        $retried = 0;
        $succeeded = 0;
        $failed = 0;
        $skippedByLimit = 0;
        $skippedByLease = 0;

        foreach ($orphans as $service) {
            $retryCount = (int) ($service->provision_data['retry_count'] ?? 0);

            if ($retryCount >= $maxRetries) {
                $skippedByLimit++;
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
            } catch (ProvisionLeaseBusyException $exception) {
                // 租约被占不是「这次开通失败」，而是「这次没轮到」：另一个执行者正在
                // 开通同一实例。不能计入 retry_count，否则一次并发撞车就消耗掉一次
                // 重试额度，把本来有救的实例推向人工介入；也不该让整批命令返回失败。
                // 计数单独一路：与「已达重试上限」混用一个 skipped 时，汇总行看不出
                // 到底是额度用尽还是并发撞车，运维无法判断该不该再加一轮。
                $retried--;
                $skippedByLease++;
                $this->line(sprintf('service_id=%d 正在由其他任务开通，本轮跳过：%s', $service->id, $exception->getMessage()));
            } catch (\Throwable $exception) {
                $failed++;
                $this->incrementRetryCount($service, $exception->getMessage());
                $this->error(sprintf('service_id=%d 重试失败：%s', $service->id, mb_substr($exception->getMessage(), 0, 120)));
            }
        }

        $this->info(sprintf(
            '重试完成：尝试 %d，成功 %d，失败 %d，跳过 %d（达上限 %d，租约被占 %d）。',
            $retried,
            $succeeded,
            $failed,
            $skippedByLimit + $skippedByLease,
            $skippedByLimit,
            $skippedByLease
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 扫描超时未履约的已付新购账单并打 requires_refund 标记（幂等，自动执行）。
     *
     * 停留时长以独立列 fulfillment_pending_at 为准（原 config_snapshot JSON 键已迁列，
     * 大表上 JSON_EXTRACT 无法走索引）。单条异常不中断整批，继续处理其余账单。
     */
    private function markStalePendingInvoices(int $staleMinutes): int
    {
        $threshold = now()->subMinutes($staleMinutes);

        try {
            // 状态与时间都走列，命中 (fulfillment_pending, fulfillment_pending_at) 复合索引；
            // requires_refund 在 SQL 侧排除，避免已标记账单每轮重复扫出来。
            $invoices = Invoice::query()
                ->where('status', InvoiceStatus::PAID)
                ->where('fulfillment_pending', true)
                ->where('requires_refund', false)
                ->whereNotNull('fulfillment_pending_at')
                ->where('fulfillment_pending_at', '<=', $threshold)
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

    /**
     * 打标「开通租约过期且累计尝试次数达上限」的服务，只标记不重试。
     *
     * 租约只覆盖「抢占—提交上游」的短窗口（180 秒），过期后允许他人接管；若同一个
     * 实例反复尝试仍未开通，说明不是并发问题而是上游侧持续失败。这里只写
     * provision_data.lease_exhausted_* 证据供人工核实上游账单状态，绝不自动重试：
     * 上游 checkout 成功但未取回 host_id 时自动重试会重复下单。
     */
    /**
     * @return array<int, Service>
     */
    private function markLeaseExhaustedServices(int $limit): array
    {
        if (! SchemaMetadataCache::hasColumn('services', 'provision_lease_until')) {
            return [];
        }

        $maxAttempts = max(1, (int) $this->option('max-retries'));
        $now = now()->format('Y-m-d H:i:s');

        try {
            $services = Service::query()
                ->whereNotNull('provision_lease_until')
                ->where('provision_lease_until', '<=', $now)
                ->where('provision_attempts', '>=', $maxAttempts)
                // 已打过标记的不重复打，保证命令可反复执行而不刷日志。
                ->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(provision_data, '$.lease_exhausted_at')), '') = ''")
                ->orderBy('id')
                ->limit($limit)
                ->get();

            if ($services->isEmpty()) {
                return [];
            }
        } catch (\Throwable $exception) {
            Log::warning('[上游开通补偿] 开通租约耗尽扫描失败，本轮跳过', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $marked = [];
        foreach ($services as $service) {
            $provisionData = (array) ($service->provision_data ?? []);
            $provisionData['lease_exhausted_at'] = $now;
            $provisionData['lease_exhausted_token'] = (string) ($service->provision_lease_token ?? '');
            $provisionData['lease_exhausted_attempts'] = (int) ($service->provision_attempts ?? 0);
            $provisionData['lease_exhausted_reason'] = '租约过期且累计开通尝试达上限，需人工核实上游账单状态';

            $service->forceFill(['provision_data' => $provisionData])->save();

            $marked[] = $service;
            Log::warning('[上游开通补偿] 开通租约已耗尽，需人工核实上游实际开通状态', [
                'service_id' => (int) $service->id,
                'order_id' => (int) ($service->order_id ?? 0),
                'provision_attempts' => (int) ($service->provision_attempts ?? 0),
                'lease_until' => (string) ($service->provision_lease_until ?? ''),
            ]);
        }

        return $marked;
    }

    /**
     * 已打人工核实标记、仍未消除的实例清单。
     *
     * 标记写在 provision_data.lease_exhausted_at 里，本身不会驱动任何动作——
     * 补偿命令是这些实例唯一的可见出口：每轮把清单打给运维，否则标记只是
     * 埋在 JSON 里的死数据，谁也不会去看。
     *
     * @return array<int, Service>
     */
    private function findLeaseExhaustedAwaitingReview(int $limit): array
    {
        if (! SchemaMetadataCache::hasColumn('services', 'provision_lease_until')) {
            return [];
        }

        try {
            return Service::query()
                ->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(provision_data, '$.lease_exhausted_at')), '') != ''")
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('[上游开通补偿] 待人工核实清单读取失败，本轮跳过', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
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
