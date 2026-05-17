<?php

namespace App\Http\Resources\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\PaymentStatus;
use App\Models\AccountTransaction;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AccountTransaction */
class FinanceLedgerResource extends JsonResource
{
    public function toArray($request): array
    {
        $amount = (float) ($this->change_amount ?? 0);
        $normalizedEventType = FinanceLedgerEventType::normalize((string) ($this->event_type ?? ''));
        $invoice = $this->whenLoaded('invoice');
        $payment = $this->whenLoaded('payment');
        $user = $this->whenLoaded('user');
        $display = $this->displayMeta($normalizedEventType, $amount, $invoice, $payment);

        return [
            'ledger_id' => (int) $this->id,
            'id' => (int) $this->id,
            'account_type' => (string) ($this->account_type ?? 'cash'),
            'event_type' => $normalizedEventType,
            'event_type_label' => FinanceLedgerEventType::label($normalizedEventType),
            'event_category' => FinanceLedgerEventType::category($normalizedEventType),
            'direction' => FinanceLedgerEventType::direction($normalizedEventType, $amount),
            'amount' => number_format(abs($amount), 2, '.', ''),
            'change_amount' => number_format($amount, 2, '.', ''),
            'balance_after' => number_format((float) ($this->balance_after ?? 0), 2, '.', ''),
            'occurred_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'remark' => (string) ($this->remark ?? ''),
            'source_type' => (string) ($this->source_type ?? ''),
            'source_id' => $this->source_id !== null ? (int) $this->source_id : null,
            'origin_type' => (string) ($this->origin_type ?? ''),
            'origin_id' => $this->origin_id !== null ? (int) $this->origin_id : null,
            'operator' => (string) ($this->operator ?? ''),
            'trace_id' => (string) ($this->trace_id ?? ''),
            'invoice' => $invoice ? [
                'id' => (int) $invoice->id,
                'invoice_no' => (string) $invoice->invoice_no,
                'type' => (string) $invoice->type,
                'type_label' => (string) ($invoice->type_label ?? InvoiceType::label((string) $invoice->type)),
                'status' => (int) $invoice->status,
                'status_label' => InvoiceStatus::$labels[(int) $invoice->status] ?? (string) $invoice->status,
                'amount' => number_format((float) $invoice->amount, 2, '.', ''),
                'paid_amount' => number_format((float) ($invoice->paid_amount ?? 0), 2, '.', ''),
            ] : null,
            'payment' => $payment ? [
                'id' => (int) $payment->id,
                'payment_no' => (string) $payment->payment_no,
                'gateway' => (string) $payment->gateway,
                'gateway_label' => $display['channel_label'],
                'status' => (int) $payment->status,
                'status_label' => PaymentStatus::$labels[(int) $payment->status] ?? (string) $payment->status,
                'trade_no' => (string) ($payment->trade_no ?? ''),
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'paid_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
            ] : null,
            'user' => $user ? [
                'id' => (int) $user->id,
                'email' => (string) $user->email,
                'nickname' => (string) ($user->nickname ?? ''),
                'display_name' => (string) ($user->display_name ?? ''),
            ] : null,
            'display' => $display,
        ];
    }

    private function displayMeta(string $eventType, float $amount, mixed $invoice, mixed $payment): array
    {
        $title = FinanceLedgerEventType::label($eventType);
        $subtitle = trim((string) ($this->remark ?? ''));
        $channelLabel = $this->gatewayLabel((string) ($payment?->gateway ?? ''));
        $status = null;
        $statusLabel = '--';

        if ($invoice) {
            $status = (int) $invoice->status;
            $statusLabel = InvoiceStatus::$labels[$status] ?? (string) $status;
        } elseif ($payment) {
            $status = (int) $payment->status;
            $statusLabel = PaymentStatus::$labels[$status] ?? (string) $status;
        }

        if ($subtitle === '') {
            $subtitle = match ($eventType) {
                FinanceLedgerEventType::INVOICE_PAYMENT => '关联账单支付',
                FinanceLedgerEventType::INVOICE_REFUND => '关联账单退款',
                FinanceLedgerEventType::RECHARGE, FinanceLedgerEventType::MANUAL_RECHARGE => '资金已入账',
                FinanceLedgerEventType::MANUAL_DEDUCTION => '管理员扣减余额',
                FinanceLedgerEventType::REFERRAL_CREDIT_CASH => '奖励转入现金余额',
                default => '账户资金调整',
            };
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'badge' => FinanceLedgerEventType::label($eventType),
            'badge_type' => $this->badgeType($eventType, $amount),
            'status' => $status,
            'status_label' => $statusLabel,
            'channel_label' => $channelLabel,
            'scene_label' => $this->sceneLabel($eventType),
        ];
    }

    private function badgeType(string $eventType, float $amount): string
    {
        return match ($eventType) {
            FinanceLedgerEventType::RECHARGE,
            FinanceLedgerEventType::MANUAL_RECHARGE,
            FinanceLedgerEventType::INVOICE_REFUND,
            FinanceLedgerEventType::REFERRAL_CREDIT_CASH => 'success',
            FinanceLedgerEventType::MANUAL_DEDUCTION,
            FinanceLedgerEventType::INVOICE_PAYMENT => 'danger',
            default => $amount < 0 ? 'danger' : 'info',
        };
    }

    private function sceneLabel(string $eventType): string
    {
        return match ($eventType) {
            FinanceLedgerEventType::RECHARGE, FinanceLedgerEventType::MANUAL_RECHARGE => '充值',
            FinanceLedgerEventType::INVOICE_PAYMENT, FinanceLedgerEventType::INVOICE_REFUND => '账单',
            FinanceLedgerEventType::MANUAL_DEDUCTION, FinanceLedgerEventType::SYSTEM_ADJUSTMENT => '调账',
            FinanceLedgerEventType::REFERRAL_CREDIT_CASH => '奖励',
            default => '资金',
        };
    }

    private function gatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            'alipay' => '支付宝支付',
            'wechat' => '微信支付',
            'balance' => '余额支付',
            'bank_transfer' => '银行转账',
            'offline' => '线下支付',
            'free' => '免费确认',
            default => $gateway !== '' ? $gateway : '手动入账',
        };
    }
}
