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
 * 口径（2026-10-20 裁决）：交付状态与退款解耦为「证据 + 开关」——
 * - off：不检查；
 * - warn（默认）：把已交付服务快照写入 finance_refunds.delivery_evidence 后放行；
 * - block：已交付直接拒绝退款。
 *
 * 「已交付」定义：账单关联订单名下存在未终止的服务实例
 * （PENDING 开通中 / ACTIVE 运行中 / SUSPENDED 已暂停 / ABNORMAL 异常均算交付事实）。
 * 存量 482 张无 service_id 的续费账单因此默认 warn，不误伤。
 */
class RefundDeliveryGuard
{
    /**
     * 检查账单交付状态；block 模式下已交付抛异常，warn 模式返回证据快照。
     *
     * @return array{mode: string, services: array<int, array<string, mixed>>}|null
     */
    public function inspect(Invoice $invoice): ?array
    {
        $mode = (string) config('finance.refund.delivery_guard', 'warn');
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

        return $evidence;
    }
}
