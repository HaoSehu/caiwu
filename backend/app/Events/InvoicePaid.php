<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Invoice;
use App\Services\Finance\InvoicePaidOrchestrator;

/**
 * 账单支付成功（支付入账完成后派发）。
 *
 * 触发点：{@see InvoicePaidOrchestrator::handlePaidInvoice}
 * ——账单状态已确认为 PAID，履约派发与优惠券同步均已调度之后。
 *
 * 监听方只承载「支付成功后」的旁路副作用（客户通知、外部埋点等）：
 * 不得反向改写支付状态，也不得让异常冒泡阻断支付主流程。
 */
final class InvoicePaid
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?string $traceId = null,
    ) {}
}
