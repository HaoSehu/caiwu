<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Service;

/**
 * 退款交付守卫（finance.refund.delivery_guard）。
 *
 * 口径（2026-10-21 裁决）：
 * - off：不检查（仅演练/回滚窗口）；
 * - warn：落交付快照后放行，但**必须**带 confirm_delivered_refund=true，否则拒绝；
 * - block（默认）：已交付直接拒绝退款。
 *
 * 「已交付」定义：账单关联订单名下存在未终止的服务实例
 * （PENDING 开通中 / ACTIVE 运行中 / SUSPENDED 已暂停 / ABNORMAL 异常均算交付事实）。
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
        if ($orderId <= 0) {
            return null;
        }

        $services = Service::query()
            ->where('order_id', $orderId)
            ->whereIn('status', [
                ServiceStatus::PENDING,
                ServiceStatus::ACTIVE,
                ServiceStatus::SUSPENDED,
                ServiceStatus::ABNORMAL,
            ])
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
