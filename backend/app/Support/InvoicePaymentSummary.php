<?php

declare(strict_types=1);

namespace App\Support;

use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\Invoice;

/**
 * 无支付单时的账单支付摘要（目前只有余额支付一种）。
 *
 * 余额支付按 ARCHITECTURE.md 的职责边界不产生 Payment 记录：它唯一的资金事实是
 * 账单名下 event_type=invoice_payment 的现金扣款台账。账单列表/详情的「支付方式」
 * 若不回落这一层，余额支付的账单会整行显示为空。
 *
 * 结构刻意与有支付单的摘要（InvoiceService::resolveInvoicePaymentSummary /
 * UserService::buildInvoicePaymentSummary）保持一致，三端按同一组字段渲染；
 * 但不给 id/payment_no：余额支付没有支付单号，编一个会让用户拿着不存在的号去对账。
 */
final class InvoicePaymentSummary
{
    /**
     * @return array<string, mixed>|null 不是余额支付时返回 null，交由调用方兜底占位符
     */
    public static function fromBalanceLedger(Invoice $invoice): ?array
    {
        if (! $invoice->relationLoaded('ledgerEntries')) {
            $invoice->loadMissing(['ledgerEntries' => fn ($query) => $query->orderByDesc('id')]);
        }

        $channel = PaymentChannelLabel::channelNameFromLedger($invoice->ledgerEntries);

        if ($channel === '') {
            return null;
        }

        return [
            'id' => 0,
            'payment_no' => '',
            'trade_no' => '',
            'gateway' => PaymentGatewayCode::BALANCE,
            'gateway_key' => PaymentGatewayCode::BALANCE,
            'gateway_label' => $channel,
            'plugin_name' => '',
            'channel_name' => $channel,
            'amount' => number_format((float) ($invoice->paid_amount ?? $invoice->amount ?? 0), 2, '.', ''),
            'status' => PaymentStatus::SUCCESS,
            'status_label' => PaymentStatus::$labels[PaymentStatus::SUCCESS] ?? '已支付',
            'paid_at' => $invoice->paid_at?->format('Y-m-d H:i:s'),
            'refund_method' => '',
            'refund_method_label' => '',
            'refund_reason' => '',
            'refunded_at' => '',
        ];
    }
}
