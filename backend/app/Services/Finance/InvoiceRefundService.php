<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Jobs\SendTemplateEmailJob;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceRefund;
use App\Models\FinanceRefundAllocation;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\Core\Data\CashMutation;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\Core\FinanceIdempotencyService;
use App\Services\Referral\ReferralService;
use App\Services\System\ActivityLogService;
use App\Services\System\NotificationService;
use App\Support\Money;
use App\Support\SchemaMetadataCache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 冲正退款流：账单退款到用户余额（系统内唯一保留的自动冲正公共入口，
 * 当前调用方为续费被取代账单的自动退款）。
 * 全款退 -> 余额回加 + INVOICE_REFUND 台账 + Refund 记录 + 红字账单
 * + Payment/Invoice/Order 收口 REFUNDED + 推广奖励回退；
 * 充值/补录账单拒退、已转余额（credited_to_balance）部分扣减口径、部分退款累计。
 */
class InvoiceRefundService
{
    public function __construct(
        private FinanceAccountService $accounts,
        private FinanceDocumentService $financeDocumentService,
        private PaymentCallbackProjector $callbackProjector,
        private ReferralService $referralService,
        private TradeLifecycleService $tradeLifecycleService,
        private FinanceIdempotencyService $idempotency,
        private RefundDeliveryGuard $deliveryGuard,
        private ActivityLogService $activityLogs,
    ) {}

    /**
     * 账单可退上限（累计口径）——全系统可退额的唯一真源。
     *
     * 口径：实收基数 max(paid_amount, amount) − 已转余额的异常支付金额 − 已完成退款累计。
     * 后台详情页的 refundable_amount / refundable_amount_for_balance / can_refund_to_balance
     * 一律调本方法（InvoiceService::adminDetail），提交时的 remainingRefundableAmount
     * 也建立在同一公式上（再扣一次已完成退款），因此展示值与守卫上限不再分叉。
     */
    public function markRefundableAmountOf(Invoice $invoice): float
    {
        $gross = round(max((float) ($invoice->paid_amount ?? 0), (float) ($invoice->amount ?? 0)), 2);

        return round(
            max($gross - $this->invoiceCreditedToBalanceAmount($invoice) - $this->completedRefundAmount($invoice), 0),
            2
        );
    }

    /**
     * 后台发起账单退款到用户余额
     *
     * @return array<string, mixed>
     */
    public function refundInvoiceToBalance(User $user, Invoice $invoice, array $payload = [], array $context = []): array
    {
        $lockKey = "lock:refund:invoice:{$invoice->id}";
        // 幂等键：管理端入口由请求层强制传入（payload 优先，直调方可放 context）；
        // 直调方（测试/内部）缺省自动生成。幂等记录在业务事务之外落库（约束见 FinanceIdempotencyService）。
        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? $context['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = 'auto:'.bin2hex(random_bytes(16));
        }

        try {
            $operation = $this->idempotency->execute(
                'invoice_refund',
                (string) ($context['operator_type'] ?? 'admin'),
                (int) ($context['operator_id'] ?? 0),
                $idempotencyKey,
                [
                    'invoice_id' => (int) $invoice->id,
                    'amount' => round((float) ($payload['amount'] ?? 0), 2),
                    'remark' => trim((string) ($payload['remark'] ?? '')),
                ],
                function () use ($user, $invoice, $payload, $context, $lockKey) {
                    return Cache::lock($lockKey, 40)->block(5, function () use ($user, $invoice, $payload, $context) {
                        return DB::transaction(function () use ($user, $invoice, $payload, $context) {
                            $lockedInvoice = Invoice::query()
                                ->lockForUpdate()
                                ->with(['order', 'payments'])
                                ->findOrFail($invoice->id);
                            $lockedUser = User::query()
                                ->lockForUpdate()
                                ->findOrFail($user->id);

                            throw_if((int) $lockedInvoice->user_id !== (int) $lockedUser->id, new BusinessException('账单与用户不匹配'));
                            throw_if(
                                ! in_array((int) $lockedInvoice->status, [InvoiceStatus::PAID], true),
                                new BusinessException('当前账单状态不支持退款')
                            );

                            // 充值账单到账时余额已同步增加、补录账单为系统外收款凭证：
                            // 两者退回余额都没有对应的资金流出，等于凭空增加余额，不得退回余额（需红字冲抵等其它方式）。
                            throw_if(
                                in_array((string) $lockedInvoice->type, [InvoiceType::RECHARGE, InvoiceType::MANUAL], true),
                                new BusinessException('充值/补录账单不支持退回余额')
                            );

                            // 系统外收款闸（2026-10-21）：补录订单/手工入账的 Payment.gateway 固定
                            // manual，该笔资金从未进入系统；对它退款等于凭空增加余额。类型闸只覆盖
                            // recharge/manual 两种账单，补录订单产出的是 type=new，必须按支付单再拦一道。
                            $invoicePayments = $lockedInvoice->payments;
                            $realPaymentCount = $invoicePayments
                                ->filter(fn ($item): bool => $item instanceof Payment
                                    && $item->gatewayKey() !== PaymentGatewayCode::MANUAL)
                                ->count();
                            if ($invoicePayments->isNotEmpty() && $realPaymentCount === 0) {
                                throw new BusinessException('该账单为系统外收款（补录/手工入账），不支持退款到余额');
                            }

                            // 纯标记通道已于 2026-10-20 裁决移除：历史 manual_mark 行只读保留，
                            // 新退款一律为「退回余额」，不再需要双通道互斥守卫。

                            // 交付守卫（finance.refund.delivery_guard）：block 已交付拒绝退款，
                            // warn 把交付快照随退款单落 delivery_evidence 证据。
                            $deliveryEvidence = $this->deliveryGuard->inspect($lockedInvoice, $payload);

                            $order = $lockedInvoice->order;

                            if ($order && (int) $order->status === OrderStatus::REFUNDED) {
                                return [
                                    'already_refunded' => true,
                                    'invoice_id' => (int) $lockedInvoice->id,
                                    'payment_id' => 0,
                                    'refund' => [],
                                ];
                            }

                            throw_if(
                                $order && (int) $order->status !== OrderStatus::PAID,
                                new BusinessException('当前订单状态不支持退款')
                            );

                            // 账单退款与订单退款同规则：奖励已释放且可提余额不足时先阻断，避免退款后无法追回返利
                            $this->referralService->assertInvoiceRewardRefundable($lockedInvoice);

                            $payment = $this->resolvePrimaryRefundablePayment($lockedInvoice);

                            if ($payment instanceof Payment && (int) $payment->status === PaymentStatus::REFUNDED) {
                                $refund = (array) data_get((array) ($payment->callback_raw ?? []), 'refund', []);
                                if ((int) $lockedInvoice->status !== InvoiceStatus::REFUNDED) {
                                    $this->tradeLifecycleService->markInvoiceRefunded(
                                        $lockedInvoice,
                                        $refund,
                                        (string) ($refund['refund_method'] ?? 'balance'),
                                        (float) ($refund['refund_amount'] ?? $refund['refund_fee'] ?? 0),
                                        $context
                                    );
                                }

                                return [
                                    'already_refunded' => true,
                                    'invoice_id' => (int) $lockedInvoice->id,
                                    'payment_id' => (int) $payment->id,
                                    'refund' => $refund,
                                ];
                            }

                            $refundableAmount = $this->remainingRefundableAmount($lockedInvoice);
                            $refundAmount = round((float) ($payload['amount'] ?? $refundableAmount), 2);
                            throw_if(
                                $refundAmount <= 0,
                                new BusinessException($this->invoiceCreditedToBalanceAmount($lockedInvoice) > 0
                                    ? '该账单款项已通过重复支付转入余额，无需再退款'
                                    : '退款金额不正确')
                            );
                            throw_if(Money::greaterThan($refundAmount, $refundableAmount), new BusinessException('退款金额超过原单可退金额'));

                            $refundReason = trim((string) ($payload['remark'] ?? ''));
                            if ($refundReason === '') {
                                $refundReason = '后台退回用户余额';
                            }

                            // 退款口径（2026-10-10 裁决）：暂时不提供原路退款——支付插件层无退款能力与契约，
                            // 只保留余额退回，refund_method 恒为 balance，不产生「原路退款」标签。
                            $refundMethod = 'balance';
                            $refundMethodLabel = trim((string) ($payload['refund_method_label'] ?? '')) ?: '退回余额';

                            $mutation = $this->accounts->credit(
                                (int) $lockedUser->id,
                                Money::toCents($refundAmount),
                                FinanceLedgerEventType::INVOICE_REFUND,
                                [
                                    'source_type' => 'invoice',
                                    'source_id' => (int) $lockedInvoice->id,
                                    'remark' => '账单退款 '.(string) $lockedInvoice->invoice_no,
                                    'lot_source_type' => 'refund',
                                    'operator_type' => (string) ($context['operator_type'] ?? 'admin'),
                                    'operator_id' => $context['operator_id'] ?? null,
                                    'operator_name' => (string) ($context['operator_name'] ?? ''),
                                    'trace_id' => (string) ($context['trace_id'] ?? ''),
                                ],
                            );
                            $transaction = FinanceLedgerEntry::query()->findOrFail($mutation->entryId);

                            // 退款血缘：把这笔退款追认到原账单的消费映射上（只写 evidence，
                            // 不动批次剩余），否则退款在账上看起来像一笔新钱，查不到对应原消费。
                            // 返回值里的 marks 是逐条消费的分摊明细，直接作为证据表的按条金额来源。
                            $lineage = $this->accounts->markRefundedConsumptions(
                                (int) $lockedUser->id,
                                'invoice',
                                (int) $lockedInvoice->id,
                                Money::toCents($refundAmount),
                            );

                            // 退款批次证据（P1-3）：退款入账本身产生一笔新批次（new_lot），
                            // 必须落到 finance_refund_allocations，否则「退的是哪一笔钱」
                            // 在账上无法回答——这正是财务核心重构的立项理由之一。
                            // 记录在退款单落库之后由 FinanceDocumentService 补写（见下）。

                            $refundRecord = [
                                'refund_method' => $refundMethod,
                                'refund_method_label' => $refundMethodLabel,
                                'refund_amount' => number_format($refundAmount, 2, '.', ''),
                                'refund_reason' => $refundReason,
                                'trade_no' => (string) ($payment?->trade_no ?? ''),
                                'original_gateway' => $payment?->gatewayKey() ?? 'balance',
                                'original_gateway_label' => $payment instanceof Payment
                                    ? $this->resolvePaymentGatewayLabel($payment->gatewayKey())
                                    : '余额支付',
                                'operator_id' => (int) ($context['operator_id'] ?? 0),
                                'operator_name' => (string) ($context['operator_name'] ?? ''),
                                'trace_id' => (string) ($context['trace_id'] ?? ''),
                                'refunded_at' => now()->format('Y-m-d H:i:s'),
                            ];

                            $documents = $this->financeDocumentService->createBalanceRefundDocuments(
                                $lockedInvoice,
                                $payment,
                                $transaction,
                                $refundAmount,
                                $refundReason,
                                array_merge($context, [
                                    'operator_type' => $context['operator_type'] ?? 'admin',
                                    'delivery_evidence' => $deliveryEvidence,
                                ]),
                            );
                            // 退款批次证据（P1-3）：new_lot 指向本次退款入账产生的新批次，
                            // consumption_mark 指向被追认的原消费（markRefundedConsumptions 已标记），
                            // 金额取 marks 里的逐条分摊额，不再统一写本次退款总额。
                            $this->recordRefundAllocations(
                                (int) $documents['refund']->id,
                                $mutation,
                                Money::toCents($refundAmount),
                                $lineage['marks'],
                            );

                            $isFullyRefunded = Money::lessThanOrEqual(abs($refundAmount - $refundableAmount), 0.0);

                            if ($payment instanceof Payment) {
                                $callbackRaw = (array) ($payment->callback_raw ?? []);
                                $callbackRaw['refund'] = $refundRecord;
                                $callbackRaw['refunds'] = array_values(array_merge(
                                    (array) ($callbackRaw['refunds'] ?? []),
                                    [$refundRecord]
                                ));

                                $paymentPayload = [
                                    'callback_raw' => $callbackRaw,
                                ];
                                if ($isFullyRefunded) {
                                    $paymentPayload['status'] = PaymentStatus::REFUNDED;
                                }
                                $payment->forceFill($paymentPayload)->save();
                                $this->callbackProjector->syncProjection($payment);
                            }

                            if ($isFullyRefunded) {
                                $this->tradeLifecycleService->markInvoiceRefunded($lockedInvoice, $refundRecord, $refundMethod, $refundAmount, $context);

                                // 全款退款回退推广奖励（幂等：订单退款流程已回退则直接跳过）
                                $refundTraceId = trim((string) ($context['trace_id'] ?? ''));
                                $this->referralService->reverseRewardForRefundedInvoice(
                                    $lockedInvoice,
                                    $refundTraceId !== '' ? "refund:{$refundTraceId}" : "refund:invoice:{$lockedInvoice->id}",
                                );
                            } else {
                                // 部分退款：状态保持 PAID，但账单/订单的 refund_amount 必须累计回写，
                                // 否则订单列表显示「部分退款」值为 0，运营会重复发起或误判可退额。
                                $this->tradeLifecycleService->markInvoicePartiallyRefunded(
                                    $lockedInvoice,
                                    $refundMethod,
                                    (string) ($context['trace_id'] ?? ''),
                                );
                            }

                            // 订单 REFUNDED 投影由状态机 markInvoiceRefunded 统一级联（原 scope 直写段已收敛）。

                            Log::info('[账单退款] 已退回用户余额', [
                                'invoice_id' => $lockedInvoice->id,
                                'invoice_no' => $lockedInvoice->invoice_no,
                                'payment_id' => $payment?->id,
                                'refund_amount' => $refundRecord['refund_amount'],
                                'user_id' => $lockedUser->id,
                            ]);

                            return [
                                'already_refunded' => false,
                                'invoice_id' => (int) $lockedInvoice->id,
                                'payment_id' => (int) ($payment?->id ?? 0),
                                'refund_id' => (int) $documents['refund']->id,
                                'refund_invoice_id' => (int) $documents['refund_invoice']->id,
                                'recharge_record_id' => $documents['recharge_record']?->id,
                                'refund' => $refundRecord,
                            ];
                        });
                    });
                },
                [
                    'resource_type' => 'invoice',
                    'resource_id' => (int) $invoice->id,
                    'trace_id' => (string) ($context['trace_id'] ?? ''),
                ],
            );
        } catch (LockTimeoutException) {
            throw new BusinessException('退款处理中，请勿重复提交');
        }

        $result = $operation->payload;

        if (! $operation->replayed) {
            // 审计走 logSafely：写失败只告警，不阻断已完成的资金动作
            $this->activityLogs->logSafely(
                'finance',
                'invoice.refund.completed',
                sprintf('账单退款到余额 ¥%s', (string) ($result['refund']['refund_amount'] ?? '')),
                'invoice',
                (int) ($result['invoice_id'] ?? $invoice->id),
                [
                    'invoice_id' => (int) ($result['invoice_id'] ?? 0),
                    'refund_id' => (int) ($result['refund_id'] ?? 0),
                    'already_refunded' => (bool) ($result['already_refunded'] ?? false),
                    'operator_name' => (string) ($context['operator_name'] ?? ''),
                    'trace_id' => (string) ($context['trace_id'] ?? ''),
                ],
            );

            $this->notifyClientInvoiceRefunded($user, $invoice, $result);
        }

        return $result;
    }

    /**
     * 客户账单退款通知（模板 100017）：事务提交后队列异步发送。
     * already_refunded（幂等短路）或无退款明细时不发；部分退款每次标记独立成事件，各发一封。
     * 发送失败仅告警，不影响退款结果。
     */
    private function notifyClientInvoiceRefunded(User $user, Invoice $invoice, array $result): void
    {
        $refund = (array) ($result['refund'] ?? []);
        if ($refund === [] || (bool) ($result['already_refunded'] ?? false)) {
            return;
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email === '') {
            return;
        }

        try {
            SendTemplateEmailJob::dispatch($email, NotificationService::TEMPLATE_INVOICE_REFUND, [
                'display_name' => trim((string) ($user->display_name ?? '')) !== '' ? (string) $user->display_name : $email,
                'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                'order_no' => (string) ($invoice->order?->order_no ?? ''),
                'product_name' => (string) ($invoice->display_product_name ?: $invoice->order?->display_product_name ?? ''),
                'refund_amount' => (string) ($refund['refund_amount'] ?? ''),
                'refund_method_label' => (string) ($refund['refund_method_label'] ?? ''),
                'refunded_at' => (string) ($refund['refunded_at'] ?? ''),
                'refund_reason' => (string) ($refund['refund_reason'] ?? ''),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('[账单退款] 客户通知派发失败', [
                'invoice_id' => (int) $invoice->id,
                'user_id' => (int) $user->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function resolvePrimaryRefundablePayment(Invoice $invoice, ?array $gateways = null): ?Payment
    {
        $payments = $invoice->relationLoaded('payments')
            ? $invoice->payments
            : Payment::query()
                ->where('invoice_id', $invoice->id)
                ->whereIn('status', [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED])
                ->orderByDesc('id')
                ->get();

        if (is_array($gateways) && $gateways !== []) {
            $payments = $payments
                ->filter(fn (Payment $payment) => in_array($payment->gatewayKey(), $gateways, true))
                ->values();
        }

        // 已转入余额的异常支付（重复支付/超额支付）不得作为主退款支付单：
        // 其金额已通过 creditCapturedPaymentToBalance 退回用户余额，再按其原路/全额退款会造成双重退款。
        $isRefundablePayment = fn (Payment $payment): bool => in_array((int) $payment->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true)
            && ! PaymentCallbackRaw::isCreditedToBalance($payment)
            // 系统外收款（补录/手工入账）不是真实资金流入，不得作为退款主支付单：
            // 否则会把系统外收到的钱再"退"进用户余额，凭空增加账面余额。
            && $payment->gatewayKey() !== PaymentGatewayCode::MANUAL;

        return $payments
            ->first(fn (Payment $payment) => $isRefundablePayment($payment)
                && ! PaymentCallbackRaw::isDuplicatePaid($payment))
            ?? $payments->first($isRefundablePayment);
    }

    private function resolveBalanceRefundableAmount(Invoice $invoice): float
    {
        // 账单级累计口径（2026-10-21 统一）：可退上限 = 实收基数 − 已转余额的异常支付金额。
        // 历史上「单笔支付单金额」分支在拆分支付（50+50）与补录+网关混合支付下会把可退额
        // 截断成单笔金额，导致退 50 就收口、剩余款永久不可退；混付分支口径本就正确，
        // 现统一收敛到同一公式，不再按支付单金额切分。
        $grossRefundable = round(max((float) ($invoice->paid_amount ?? 0), (float) ($invoice->amount ?? 0)), 2);

        // 扣除已转入余额的异常支付（重复支付/超额支付）金额：
        // 该部分已退回用户余额，若退款时未扣除会造成与"转入余额"重复返还。
        return round(max($grossRefundable - $this->invoiceCreditedToBalanceAmount($invoice), 0), 2);
    }

    /**
     * 账单名下累计已转入余额的异常支付金额（creditCapturedPaymentToBalance 产生）。
     */
    private function invoiceCreditedToBalanceAmount(Invoice $invoice): float
    {
        $payments = $invoice->relationLoaded('payments')
            ? $invoice->payments
            : Payment::query()
                ->where('invoice_id', (int) $invoice->id)
                ->where('status', PaymentStatus::SUCCESS)
                ->get();

        return round((float) $payments
            ->filter(fn (Payment $payment) => PaymentCallbackRaw::isCreditedToBalance($payment))
            ->sum(fn (Payment $payment) => (float) (
                PaymentCallbackRaw::creditedAmount($payment) > 0
                    ? PaymentCallbackRaw::creditedAmount($payment)
                    : (float) ($payment->amount ?? 0)
            )), 2);
    }

    private function remainingRefundableAmount(Invoice $invoice): float
    {
        $refundedAmount = $this->completedRefundAmount($invoice);

        return round(max($this->resolveBalanceRefundableAmount($invoice) - $refundedAmount, 0), 2);
    }

    private function completedRefundAmount(Invoice $invoice): float
    {
        // 口径统一（2026-10-21）：真实退款（funds_moved=1）与历史纯标记（funds_moved=0）
        // 同表记录、都是「已确认退过」的事实，累计必须全部计入；只算 funds_moved=1
        // 会让「先标记 30、再真退 100」叠出 130 的超额退款。
        return round((float) FinanceRefund::query()
            ->where('invoice_id', (int) $invoice->id)
            ->where('status', FinanceRefund::STATUS_COMPLETED)
            ->sum('amount'), 2);
    }

    /**
     * 写入退款批次证据（P1-3）。
     *
     * new_lot：退款入账（credit）产生的新批次，一笔退款必有且仅有一条；
     * consumption_mark：被本次退款追认的原消费行，按 markRefundedConsumptions 的标记结果取。
     * 幂等：allocation_key 唯一，重复调用被忽略。
     *
     * @param  CashMutation  $mutation  退款入账结果（含 allocations）
     * @param  list<array{consumption_id: int, amount_cents: int}>  $marks  本次退款逐条消费的分摊明细
     */
    private function recordRefundAllocations(
        int $refundId,
        CashMutation $mutation,
        int $amountCents,
        array $marks = [],
    ): void {
        if ($refundId <= 0 || ! SchemaMetadataCache::hasTable('finance_refund_allocations')) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($mutation->allocations as $allocation) {
            $lotId = (int) $allocation['lot_id'];
            if ($lotId <= 0) {
                continue;
            }

            $rows[] = [
                'allocation_key' => 'refund:'.$refundId.':new_lot:'.$lotId,
                'refund_id' => $refundId,
                'allocation_type' => FinanceRefundAllocation::TYPE_NEW_LOT,
                'lot_id' => $lotId,
                'consumption_id' => $allocation['consumption_id'] ?? null,
                'amount' => Money::formatCents($amountCents),
                'created_at' => $now,
            ];
        }

        // 原消费映射：amount 必须是「这一条消费本次分摊到的金额」，不是本次退款总额
        // （表定义：amount = 分配金额）。金额来自 markRefundedConsumptions 的 marks 明细，
        // 由 allocator 按 min(可追认额度, 剩余) 逐笔算好；一条退款跨多笔消费时各自一行、合计等于退款额。
        // 保留 20 条上限：单次退款在上百条消费上分摊时不把证据表写爆，超出部分不落证据行。
        foreach (array_slice($marks, 0, 20) as $mark) {
            $consumptionId = (int) $mark['consumption_id'];
            if ($consumptionId <= 0) {
                continue;
            }

            $rows[] = [
                'allocation_key' => 'refund:'.$refundId.':consumption:'.$consumptionId,
                'refund_id' => $refundId,
                'allocation_type' => FinanceRefundAllocation::TYPE_CONSUMPTION_MARK,
                'lot_id' => null,
                'consumption_id' => $consumptionId,
                'amount' => Money::formatCents((int) $mark['amount_cents']),
                'created_at' => $now,
            ];
        }

        foreach ($rows as $row) {
            DB::table('finance_refund_allocations')->insertOrIgnore($row);
        }
    }

    private function resolvePaymentGatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            // 与 PaymentGatewayCode::LABELS 一致的词条统一走集中定义，避免多处文案漂移。
            PaymentGatewayCode::ALIPAY,
            PaymentGatewayCode::YIPAY,
            PaymentGatewayCode::WECHAT,
            PaymentGatewayCode::BALANCE => PaymentGatewayCode::label($gateway),
            // 业务侧扩展口径与本地历史文案不属于集中 LABELS，保留原样。
            'bank_transfer' => '银行转账',
            'offline' => '线下支付',
            default => '手动入账',
        };
    }
}
