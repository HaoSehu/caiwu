<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;

/**
 * 退款交付守卫（finance.refund.delivery_guard）。
 *
 * 口径（2026-10-21 裁决）：
 * - off：不检查（仅演练/回滚窗口）；
 * - warn：落交付快照后放行，但**必须**带 confirm_delivered_refund=true，否则拒绝；
 * - block（默认）：已交付直接拒绝退款。
 *
 * 「已交付」定义：账单经三路（services.order_id / order.service_id 反查 / services.invoice_id）
 * 能命中未终止的服务实例（PENDING 开通中 / ACTIVE 运行中 / SUSPENDED 已暂停 / ABNORMAL 异常均算交付事实）。
 * 续费账单只回写 service.invoice_id、不改 order_id，必须走 invoice_id 那一路，否则会漏判。
 * 默认 block 的原因：已复现「服务仍在跑 + 全额退款」双吃，属资金损失；
 * 存量无 service_id 的账单查不到交付事实，放行等于保留漏洞。
 */
class RefundDeliveryGuard
{
    /**
     * 检查账单交付状态；block 模式已交付抛异常，warn 模式未二次确认也抛异常。
     *
     * @param  array<string, mixed>  $context  退款载荷上下文（含 confirm_delivered_refund 标记）
     * @return array{mode: string, services: array<int, array<string, mixed>>}|null
     */
    public function inspect(Invoice $invoice, array $context = []): ?array
    {
        $mode = (string) config('finance.refund.delivery_guard', 'block');
        if ($mode === 'off') {
            return null;
        }

        $orderId = (int) ($invoice->order_id ?? 0);
        $invoiceId = (int) ($invoice->id ?? 0);

        // 续费账单可能没有订单（order_id<=0），此时仍要按 invoice_id 兜底，不能直接放行。
        if ($orderId <= 0 && $invoiceId <= 0) {
            return null;
        }

        // 订单主线上可能已经回填的服务：续费成功后影子订单会补记 service_id。
        $relatedServiceIds = [];
        if ($orderId > 0) {
            $order = Order::query()->find($orderId, ['id', 'service_id']);
            if ($order !== null && (int) ($order->service_id ?? 0) > 0) {
                $relatedServiceIds[] = (int) $order->service_id;
            }
        }

        // 三路合并去重判定「已交付」，缺一路都会漏判：
        // 1) services.order_id = 账单订单：首开账单的主线；
        // 2) services.id = order.service_id：续费成功后影子订单回填的服务关联；
        // 3) services.invoice_id = 账单 ID：ServiceRenewService 续费只改 service.invoice_id、
        //    不改 order_id（服务仍挂在首开订单上），只看 order_id 会把续费账单漏判成「未交付」
        //    （实测 invoice 3035/order 3095 守卫命中 0 个服务，但按订单反查能命中 ACTIVE 服务）。
        $services = Service::query()
            ->whereIn('status', [
                ServiceStatus::PENDING,
                ServiceStatus::ACTIVE,
                ServiceStatus::SUSPENDED,
                ServiceStatus::ABNORMAL,
            ])
            ->where(function (Builder $query) use ($orderId, $relatedServiceIds, $invoiceId): void {
                $hasCondition = false;

                if ($orderId > 0) {
                    $query->where('order_id', $orderId);
                    $hasCondition = true;
                }

                if ($relatedServiceIds !== []) {
                    if ($hasCondition) {
                        $query->orWhereIn('id', $relatedServiceIds);
                    } else {
                        $query->whereIn('id', $relatedServiceIds);
                    }
                    $hasCondition = true;
                }

                if ($invoiceId > 0) {
                    if ($hasCondition) {
                        $query->orWhere('invoice_id', $invoiceId);
                    } else {
                        $query->where('invoice_id', $invoiceId);
                    }
                    $hasCondition = true;
                }

                // 三路都没有可用条件时不允许退化成全表扫描。
                if (! $hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderBy('id')
            ->get(['id', 'name', 'domain', 'status']);

        if ($services->isEmpty()) {
            return null;
        }

        $evidence = [
            'mode' => $mode,
            'services' => $services
                ->map(static fn (Service $service): array => [
                    'id' => (int) $service->id,
                    'name' => (string) $service->name,
                    'domain' => (string) ($service->domain ?? ''),
                    'status' => (int) $service->status,
                ])
                ->all(),
        ];

        if ($mode === 'block') {
            throw new BusinessException(
                '账单关联服务已交付（共 '.$services->count().' 个实例），当前配置禁止退款；请先走服务终止流程',
                42200,
            );
        }

        // warn 模式是演练/回滚窗口的临时降级，不能成为「静默放行」：
        // 调用方必须显式传入二次确认标记，否则一律拒绝，避免默认值漂移时无声放水。
        if ($mode === 'warn' && ! ($context['confirm_delivered_refund'] ?? false)) {
            throw new BusinessException(
                '账单关联服务已交付（共 '.$services->count().' 个实例），退款需二次确认（confirm_delivered_refund=true）',
                42200,
            );
        }

        return $evidence;
    }
}
