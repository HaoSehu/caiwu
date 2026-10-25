<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Constants\PaymentGatewayCode;
use App\Events\InvoicePaid;
use App\Jobs\SendTemplateEmailJob;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\InvoicePaidOrchestrator;
use App\Services\System\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 客户付款成功通知（模板 100016）。
 *
 * handlePaidInvoice 存在重入/对账重放，以 AutomationLog 按账单+用户幂等，
 * 同一账单只发一次；派发失败仅告警，不影响履约主流程。
 *
 * 监听器自身吞掉全部异常：支付编排是同步派发事件，监听器抛出会打断
 * 调用方 {@see InvoicePaidOrchestrator::handlePaidInvoice}
 * 后续的信号量释放与耗时日志。
 */
class SendInvoicePaidClientNotification
{
    public function handle(InvoicePaid $event): void
    {
        try {
            $this->notify($event->invoice);
        } catch (\Throwable $exception) {
            Log::warning('[购买链路] 客户付款成功通知监听器异常', [
                'invoice_id' => (int) $event->invoice->id,
                'trace_id' => (string) ($event->traceId ?? ''),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
    }

    private function notify(Invoice $invoice): void
    {
        $userId = (int) ($invoice->user_id ?? 0);
        if ($userId <= 0) {
            return;
        }

        // 先判收件地址再落幂等记录：纯手机号账号无邮箱时不动幂等键，
        // 用户后续补绑邮箱后的重入/对账重放仍可补发本通知。
        $user = $invoice->user;
        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return;
        }

        if (! AutomationLog::recordOnce(
            'client-payment-notify', 'invoice_paid', 'invoice', (int) $invoice->id, 'user:'.$userId
        )) {
            return;
        }

        $payment = Payment::query()
            ->where('invoice_id', (int) $invoice->id)
            ->whereGatewayKeyIn(PaymentGatewayCode::thirdPartyGateways())
            ->where('status', 1)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first(Payment::gatewayProjectionColumns([
                'id',
                'invoice_id',
                'plugin_id',
                'trade_no',
                'paid_at',
            ]));

        try {
            SendTemplateEmailJob::dispatch($email, NotificationService::TEMPLATE_PAYMENT_SUCCESS, [
                'display_name' => trim((string) ($user->display_name ?? '')) !== '' ? (string) $user->display_name : $email,
                'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                'order_no' => (string) ($invoice->order->order_no ?? ''),
                'product_name' => (string) ($invoice->order->display_product_name ?: $invoice->display_product_name),
                'paid_amount' => number_format((float) ($invoice->paid_amount ?? $invoice->amount ?? 0), 2, '.', ''),
                'payment_method' => PaymentGatewayCode::displayLabel($payment?->gatewayKey() ?? ''),
                'trade_no' => (string) ($payment->trade_no ?? ''),
                'paid_at' => $invoice->paid_at instanceof Carbon
                    ? $invoice->paid_at->format('Y-m-d H:i:s')
                    : ($payment?->paid_at instanceof Carbon
                        ? $payment->paid_at->format('Y-m-d H:i:s')
                        : now()->format('Y-m-d H:i:s')),
                'notice_message' => '请留意后续开通与交付进度，如有疑问请联系客服或查看账单详情。',
            ]);
            AutomationLog::markExecuted(
                'client-payment-notify', 'invoice_paid', 'invoice', (int) $invoice->id, 'user:'.$userId,
                ['email' => $email, 'template' => NotificationService::TEMPLATE_PAYMENT_SUCCESS]
            );
        } catch (\Throwable $exception) {
            AutomationLog::forgetRecord(
                'client-payment-notify', 'invoice_paid', 'invoice', (int) $invoice->id, 'user:'.$userId
            );
            Log::warning('[购买链路] 客户付款成功通知派发失败', [
                'invoice_id' => (int) $invoice->id,
                'user_id' => $userId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
