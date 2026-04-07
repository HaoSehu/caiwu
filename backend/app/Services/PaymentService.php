<?php

declare(strict_types=1);

namespace App\Services;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\AccountTransaction;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAccount;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private ProvisionService $provisionService,
        private AlipayFaceToFaceService $alipayService,
        private ServiceRenewService $serviceRenewService,
        private ReferralService $referralService,
        private PaidOrderBusinessFlowDispatcher $paidOrderBusinessFlowDispatcher,
        private AdminOrderNotificationService $adminOrderNotificationService,
    ) {}

    /**
     * 余额支付
     */
    public function payByBalance(Invoice $invoice, User $user, array $context = []): Payment
    {
        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $lockKey = "lock:pay:invoice:{$invoice->id}";

        $payment = $this->withLock($lockKey, 30, function () use ($invoice, $user, $traceId) {
            return DB::transaction(function () use ($invoice, $user, $traceId) {
                $lockedInvoice = Invoice::query()
                    ->lockForUpdate()
                    ->with('order')
                    ->findOrFail($invoice->id);
                $lockedUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail($user->id);
                $lockedAccount = $this->lockUserAccount($lockedUser->id);

                throw_if(
                    ! in_array((int) $lockedInvoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE], true),
                    new BusinessException('账单状态异常，无法支付')
                );

                $amount = round((float) $lockedInvoice->amount - (float) ($lockedInvoice->paid_amount ?? 0), 2);
                throw_if($amount <= 0, new BusinessException('当前账单无需支付'));
                throw_if((float) $lockedAccount->cash_balance < $amount, new BusinessException('余额不足'));

                $lockedAccount->forceFill([
                    'cash_balance' => round((float) $lockedAccount->cash_balance - $amount, 2),
                ])->save();

                AccountTransaction::query()->create([
                    'user_id'      => $lockedUser->id,
                    'account_type' => 'cash',
                    'event_type'   => 'consume',
                    'change_amount' => -$amount,
                    'balance_after' => $lockedAccount->cash_balance,
                    'source_type'  => 'invoice',
                    'source_id'    => $lockedInvoice->id,
                    'origin_type'  => 'invoice',
                    'origin_id'    => $lockedInvoice->id,
                    'remark'       => "支付账单 {$lockedInvoice->invoice_no}",
                ]);

                $payment = Payment::query()->create([
                    'payment_no' => Payment::generatePaymentNo(),
                    'user_id' => $lockedUser->id,
                    'invoice_id' => $lockedInvoice->id,
                    'gateway' => 'balance',
                    'amount' => $amount,
                    'status' => PaymentStatus::SUCCESS,
                    'callback_raw' => [
                        'source' => 'balance',
                        'trace_id' => $traceId,
                    ],
                    'paid_at' => now(),
                ]);

                $lockedInvoice->forceFill([
                    'status' => InvoiceStatus::PAID,
                    'paid_amount' => $lockedInvoice->amount,
                    'paid_at' => now(),
                ])->save();

                $lockedInvoice->order?->forceFill([
                    'status' => OrderStatus::PAID,
                    'paid_amount' => $amount,
                    'paid_at' => now(),
                ])->save();

                $this->closeOtherPendingPayments($lockedInvoice, (int) $payment->id, 'invoice_paid_by_balance');

                return $payment;
            });
        }, '支付请求处理中，请勿重复提交');

        $this->handlePaidInvoice($invoice, $traceId !== '' ? 'balance:' . $traceId : 'balance:' . $invoice->id);

        return $payment->fresh() ?? $payment;
    }

    /**
     * 充值（管理员手动）
     */
    public function recharge(User $user, float $amount, string $remark = '手动充值'): void
    {
        DB::transaction(function () use ($user, $amount, $remark) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $account = $this->lockUserAccount($lockedUser->id);
            $account->forceFill([
                'cash_balance' => round((float) $account->cash_balance + $amount, 2),
            ])->save();

            AccountTransaction::create([
                'user_id'      => $lockedUser->id,
                'account_type' => 'cash',
                'event_type'   => 'recharge',
                'change_amount' => $amount,
                'balance_after' => $account->cash_balance,
                'origin_type'  => 'manual_recharge',
                'origin_id'    => $lockedUser->id,
                'remark'       => $remark,
            ]);
        });
    }

    /**
     * 支付宝充值 — 预下单
     */
    public function rechargeByAlipay(User $user, float $amount): array
    {
        throw_if(
            !$this->alipayService->isEnabled(),
            new BusinessException('支付宝支付未启用')
        );
        throw_if($amount < 1, new BusinessException('充值金额不能小于 1 元'));
        throw_if($amount > 50000, new BusinessException('单笔充值不能超过 50000 元'));

        $normalizedAmount = round($amount, 2);
        $lockKey = 'lock:recharge:create:' . $user->id . ':' . md5(number_format($normalizedAmount, 2, '.', ''));

        $payment = $this->withLock($lockKey, 20, function () use ($user, $normalizedAmount) {
            return DB::transaction(function () use ($user, $normalizedAmount) {
                $payment = Payment::query()
                    ->where('user_id', $user->id)
                    ->whereNull('invoice_id')
                    ->where('gateway', 'alipay')
                    ->where('status', PaymentStatus::PENDING)
                    ->where('amount', $normalizedAmount)
                    ->where('created_at', '>=', now()->subMinutes(15))
                    ->lockForUpdate()
                    ->latest('id')
                    ->first();

                if ($payment) {
                    return $payment;
                }

                return Payment::query()->create([
                    'payment_no' => Payment::generatePaymentNo(),
                    'user_id'    => $user->id,
                    'invoice_id' => null,
                    'gateway'    => 'alipay',
                    'amount'     => $normalizedAmount,
                    'status'     => PaymentStatus::PENDING,
                ]);
            });
        }, '充值请求处理中，请勿重复提交');

        $subject = config('app.name', 'IDC') . ' - 账户充值 ¥' . number_format($normalizedAmount, 2, '.', '');
        $result  = $this->alipayService->precreate($payment->payment_no, $normalizedAmount, $subject);

        $payment->forceFill([
            'callback_raw' => array_merge((array) ($payment->callback_raw ?? []), [
                'source' => 'alipay_recharge_precreate',
            ]),
        ])->save();

        return [
            'payment_no' => $payment->payment_no,
            'qr_code'    => $result['qr_code'],
            'amount'     => number_format($normalizedAmount, 2, '.', ''),
        ];
    }

    /**
     * 轮询充值支付状态
     */
    public function queryRechargeStatus(Payment $payment): array
    {
        if ($payment->status === PaymentStatus::SUCCESS) {
            return ['paid' => true, 'trade_no' => $payment->trade_no];
        }

        $result = $this->alipayService->query($payment->payment_no);

        if (in_array($result['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            $this->completeRechargePayment($payment, $result['trade_no'], $result['raw']);
            return ['paid' => true, 'trade_no' => $result['trade_no']];
        }

        return ['paid' => false, 'trade_status' => $result['trade_status']];
    }

    /**
     * 完成充值到账
     */
    private function completeRechargePayment(Payment $payment, string $tradeNo, array $raw = []): void
    {
        $lockKey = "lock:recharge:payment:{$payment->id}";

        Cache::lock($lockKey, 30)->block(5, function () use ($payment, $tradeNo, $raw) {
            $payment->refresh();
            if ($payment->status === PaymentStatus::SUCCESS) {
                return;
            }

            DB::transaction(function () use ($payment, $tradeNo, $raw) {
                $payment->update([
                    'trade_no'     => $tradeNo,
                    'status'       => PaymentStatus::SUCCESS,
                    'callback_raw' => $raw,
                    'paid_at'      => now(),
                ]);

                $user = User::query()->lockForUpdate()->findOrFail($payment->user_id);
                $account = $this->lockUserAccount($user->id);
                $account->forceFill([
                    'cash_balance' => round((float) $account->cash_balance + (float) $payment->amount, 2),
                ])->save();

                AccountTransaction::create([
                    'user_id'      => $user->id,
                    'account_type' => 'cash',
                    'event_type'   => 'recharge',
                    'change_amount' => $payment->amount,
                    'balance_after' => $account->cash_balance,
                    'source_type'  => 'payment',
                    'source_id'    => $payment->id,
                    'origin_type'  => 'alipay_recharge',
                    'origin_id'    => $payment->id,
                    'remark'       => "支付宝充值 {$payment->payment_no}",
                ]);
            });
        });
    }

    /**
     * 支付宝当面付 — 预下单，返回二维码
     */
    public function payByAlipay(Invoice $invoice, User $user, array $context = []): array
    {
        throw_if(
            !$this->alipayService->isEnabled(),
            new BusinessException('支付宝支付未启用')
        );

        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $lockKey = "lock:pay:alipay:invoice:{$invoice->id}";

        $payload = $this->withLock($lockKey, 20, function () use ($invoice, $user, $traceId) {
            return DB::transaction(function () use ($invoice, $user, $traceId) {
                $lockedInvoice = Invoice::query()
                    ->lockForUpdate()
                    ->with('order')
                    ->findOrFail($invoice->id);

                throw_if(
                    ! in_array((int) $lockedInvoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE], true),
                    new BusinessException('账单状态异常，无法支付')
                );

                $amount = round((float) $lockedInvoice->amount - (float) ($lockedInvoice->paid_amount ?? 0), 2);
                throw_if($amount <= 0, new BusinessException('无需支付'));

                $payment = Payment::query()
                    ->where('invoice_id', $lockedInvoice->id)
                    ->where('gateway', 'alipay')
                    ->where('status', PaymentStatus::PENDING)
                    ->latest('id')
                    ->first();

                if (! $payment) {
                    $payment = Payment::query()->create([
                        'payment_no' => Payment::generatePaymentNo(),
                        'user_id' => $user->id,
                        'invoice_id' => $lockedInvoice->id,
                        'gateway' => 'alipay',
                        'amount' => $amount,
                        'status' => PaymentStatus::PENDING,
                        'callback_raw' => [
                            'source' => 'alipay_precreate',
                            'trace_id' => $traceId,
                        ],
                    ]);
                }

                return [
                    'invoice' => $lockedInvoice,
                    'payment' => $payment,
                ];
            });
        }, '支付二维码生成中，请稍后重试');

        /** @var Invoice $lockedInvoice */
        $lockedInvoice = $payload['invoice'];
        /** @var Payment $payment */
        $payment = $payload['payment'];

        $subject = config('app.name', 'IDC') . ' - 订单 ' . ($lockedInvoice->order?->order_no ?? $lockedInvoice->invoice_no);
        $result  = $this->alipayService->precreate($payment->payment_no, (float) $payment->amount, $subject);

        return [
            'payment_no' => $payment->payment_no,
            'qr_code'    => $result['qr_code'],
            'amount'     => number_format((float) $payment->amount, 2, '.', ''),
        ];
    }

    /**
     * 支付宝异步通知处理
     */
    public function handleAlipayNotify(array $params): bool
    {
        if (! $this->alipayService->verifyNotify($params)) {
            Log::warning('[支付宝回调] 签名验证失败', [
                'payment_no' => (string) ($params['out_trade_no'] ?? ''),
                'trade_no' => (string) ($params['trade_no'] ?? ''),
                'trade_status' => (string) ($params['trade_status'] ?? ''),
                'app_id' => (string) ($params['app_id'] ?? ''),
            ]);
            return false;
        }

        $paymentNo   = $params['out_trade_no'] ?? '';
        $tradeStatus = $params['trade_status'] ?? '';
        $tradeNo     = $params['trade_no'] ?? '';

        if (! in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            Log::info('[支付宝回调] 非成功状态，跳过', ['trade_status' => $tradeStatus]);
            return true;
        }

        $payment = Payment::where('payment_no', $paymentNo)->first();
        if (! $payment) {
            Log::warning('[支付宝回调] 支付记录不存在', ['payment_no' => $paymentNo]);
            return false;
        }

        if (! $this->alipayService->matchesAppId($params['app_id'] ?? '')) {
            Log::warning('[支付宝回调] app_id 不匹配', [
                'payment_no' => $paymentNo,
                'app_id' => (string) ($params['app_id'] ?? ''),
            ]);

            return false;
        }

        $notifyAmount = round((float) ($params['total_amount'] ?? 0), 2);
        $expectedAmount = round((float) $payment->amount, 2);
        if ($notifyAmount <= 0 || abs($notifyAmount - $expectedAmount) > 0.0001) {
            Log::warning('[支付宝回调] 金额校验失败', [
                'payment_no' => $paymentNo,
                'expected_amount' => $expectedAmount,
                'notify_amount' => $notifyAmount,
            ]);

            return false;
        }

        // 幂等：已处理过
        if ($payment->status === PaymentStatus::SUCCESS) {
            return true;
        }

        // 充值类（无 invoice_id）走充值到账逻辑
        if (! $payment->invoice_id) {
            $this->completeRechargePayment($payment, $tradeNo, $params);
            return true;
        }

        $lockKey = "lock:alipay:notify:{$payment->id}";

        try {
            $result = $this->withLock($lockKey, 30, function () use ($payment, $tradeNo, $params) {
                return DB::transaction(function () use ($payment, $tradeNo, $params) {
                    $lockedPayment = Payment::query()
                        ->lockForUpdate()
                        ->with(['invoice.order'])
                        ->find($payment->id);

                    if (! $lockedPayment) {
                        return [
                            'dispatch' => false,
                            'invoice' => null,
                            'payment_no' => '',
                        ];
                    }

                    if ((int) $lockedPayment->status === PaymentStatus::SUCCESS) {
                        return [
                            'dispatch' => false,
                            'invoice' => $lockedPayment->invoice,
                            'payment_no' => (string) $lockedPayment->payment_no,
                        ];
                    }

                    $invoice = $lockedPayment->invoice_id
                        ? Invoice::query()->lockForUpdate()->with('order')->find($lockedPayment->invoice_id)
                        : null;

                    if (! $invoice) {
                        return [
                            'dispatch' => false,
                            'invoice' => null,
                            'payment_no' => (string) $lockedPayment->payment_no,
                        ];
                    }

                    if ((int) $invoice->status === InvoiceStatus::PAID) {
                        $lockedPayment->forceFill([
                            'trade_no' => $tradeNo,
                            'status' => PaymentStatus::SUCCESS,
                            'callback_raw' => array_merge($params, [
                                'duplicate_paid' => true,
                                'ignored_business_update' => true,
                            ]),
                            'paid_at' => now(),
                        ])->save();

                        Log::warning('[支付宝回调] 检测到重复支付回调，已拦截二次入账', [
                            'payment_no' => $lockedPayment->payment_no,
                            'invoice_id' => $invoice->id,
                            'order_id' => $invoice->order?->id,
                        ]);

                        return [
                            'dispatch' => false,
                            'invoice' => $invoice,
                            'payment_no' => (string) $lockedPayment->payment_no,
                        ];
                    }

                    if ((int) $invoice->status === InvoiceStatus::CANCELLED) {
                        $lockedPayment->forceFill([
                            'trade_no' => $tradeNo,
                            'status' => PaymentStatus::FAILED,
                            'callback_raw' => array_merge($params, [
                                'cancelled_invoice' => true,
                                'ignored_business_update' => true,
                            ]),
                        ])->save();

                        Log::warning('[支付宝回调] 已取消账单收到支付回调，已拦截入账', [
                            'payment_no' => $lockedPayment->payment_no,
                            'invoice_id' => $invoice->id,
                            'order_id' => $invoice->order?->id,
                        ]);

                        return [
                            'dispatch' => false,
                            'invoice' => $invoice,
                            'payment_no' => (string) $lockedPayment->payment_no,
                        ];
                    }

                    throw_if(
                        ! in_array((int) $invoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE], true),
                        new BusinessException('账单状态异常，无法处理支付回调')
                    );

                    $lockedPayment->forceFill([
                        'trade_no' => $tradeNo,
                        'status' => PaymentStatus::SUCCESS,
                        'callback_raw' => $params,
                        'paid_at' => now(),
                    ])->save();

                    $invoice->forceFill([
                        'status' => InvoiceStatus::PAID,
                        'paid_amount' => $invoice->amount,
                        'paid_at' => now(),
                    ])->save();

                    $invoice->order?->forceFill([
                        'status' => OrderStatus::PAID,
                        'paid_amount' => $lockedPayment->amount,
                        'paid_at' => now(),
                    ])->save();

                    $this->closeOtherPendingPayments($invoice, (int) $lockedPayment->id, 'invoice_paid_by_alipay');

                    return [
                        'dispatch' => true,
                        'invoice' => $invoice,
                        'payment_no' => (string) $lockedPayment->payment_no,
                    ];
                });
            }, '支付回调处理中，请稍后重试');
        } catch (BusinessException $exception) {
            Log::warning('[支付宝回调] 处理失败', [
                'payment_no' => $paymentNo,
                'trade_no' => $tradeNo,
                'message' => $exception->getMessage(),
            ]);

            return false;
        } catch (\Throwable $exception) {
            Log::error('[支付宝回调] 处理异常', [
                'payment_no' => $paymentNo,
                'trade_no' => $tradeNo,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return false;
        }

        if (($result['dispatch'] ?? false) && ($result['invoice'] ?? null) instanceof Invoice) {
            $this->handlePaidInvoice($result['invoice'], 'alipay:' . ($result['payment_no'] ?? $payment->payment_no));
        }

        return true;
    }

    /**
     * 轮询支付宝订单状态
     *
     * 注意：主动查询（alipay.trade.query）的响应数据格式与异步通知不同，
     *       不能复用 handleAlipayNotify() 的签名验证逻辑，
     *       需要独立处理支付成功的入账流程。
     */
    public function queryAlipayStatus(Payment $payment): array
    {
        if ((int) $payment->status === PaymentStatus::SUCCESS) {
            return ['paid' => true, 'trade_no' => $payment->trade_no];
        }

        $result = $this->alipayService->query($payment->payment_no);

        if (in_array($result['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])) {
            // 主动查询确认已支付，直接走入账流程（不经过签名验证）
            $this->completePaymentFromQuery($payment, $result);
            $payment->refresh();
            return [
                'paid' => (int) $payment->status === PaymentStatus::SUCCESS,
                'trade_no' => $payment->trade_no ?: $result['trade_no'],
                'trade_status' => $result['trade_status'],
            ];
        }

        return ['paid' => false, 'trade_status' => $result['trade_status']];
    }

    /**
     * 主动查询确认支付成功后的入账处理
     * 与异步通知共享相同的入账逻辑，但跳过签名验证（主动查询响应无签名）
     */
    private function completePaymentFromQuery(Payment $payment, array $queryResult): void
    {
        $tradeNo = $queryResult['trade_no'] ?? '';
        $tradeStatus = $queryResult['trade_status'] ?? '';

        // 金额校验
        $queryAmount = round((float) ($queryResult['total_amount'] ?? 0), 2);
        $expectedAmount = round((float) $payment->amount, 2);
        if ($queryAmount > 0 && abs($queryAmount - $expectedAmount) > 0.0001) {
            Log::warning('[支付宝主动查询] 金额不匹配', [
                'payment_no' => $payment->payment_no,
                'expected' => $expectedAmount,
                'query' => $queryAmount,
            ]);
            return;
        }

        // 幂等：已处理过
        if ((int) $payment->status === PaymentStatus::SUCCESS) {
            return;
        }

        // 充值类（无 invoice_id）走充值到账逻辑
        if (! $payment->invoice_id) {
            $this->completeRechargePayment($payment, $tradeNo, array_merge($queryResult['raw'] ?? [], [
                'out_trade_no' => $payment->payment_no,
                'trade_no' => $tradeNo,
                'trade_status' => $tradeStatus,
                'source' => 'active_query',
            ]));
            return;
        }

        $lockKey = "lock:alipay:query:{$payment->id}";

        try {
            $result = $this->withLock($lockKey, 30, function () use ($payment, $tradeNo, $tradeStatus, $queryResult) {
                return DB::transaction(function () use ($payment, $tradeNo, $tradeStatus, $queryResult) {
                    $lockedPayment = Payment::query()
                        ->lockForUpdate()
                        ->with(['invoice.order'])
                        ->find($payment->id);

                    if (! $lockedPayment || (int) $lockedPayment->status === PaymentStatus::SUCCESS) {
                        return ['dispatch' => false, 'invoice' => $lockedPayment?->invoice, 'payment_no' => (string) ($lockedPayment?->payment_no ?? '')];
                    }

                    $invoice = $lockedPayment->invoice_id
                        ? Invoice::query()->lockForUpdate()->with('order')->find($lockedPayment->invoice_id)
                        : null;

                    if (! $invoice) {
                        return ['dispatch' => false, 'invoice' => null, 'payment_no' => (string) $lockedPayment->payment_no];
                    }

                    // 已支付账单 → 标记 duplicate_paid
                    if ((int) $invoice->status === InvoiceStatus::PAID) {
                        $lockedPayment->forceFill([
                            'trade_no' => $tradeNo,
                            'status' => PaymentStatus::SUCCESS,
                            'callback_raw' => array_merge($queryResult['raw'] ?? [], [
                                'source' => 'active_query',
                                'duplicate_paid' => true,
                            ]),
                            'paid_at' => now(),
                        ])->save();

                        Log::warning('[支付宝主动查询] 检测到重复支付，已拦截二次入账', [
                            'payment_no' => $lockedPayment->payment_no,
                            'invoice_id' => $invoice->id,
                        ]);

                        return ['dispatch' => false, 'invoice' => $invoice, 'payment_no' => (string) $lockedPayment->payment_no];
                    }

                    // 已取消账单 → 标记 FAILED
                    if ((int) $invoice->status === InvoiceStatus::CANCELLED) {
                        $lockedPayment->forceFill([
                            'trade_no' => $tradeNo,
                            'status' => PaymentStatus::FAILED,
                            'callback_raw' => array_merge($queryResult['raw'] ?? [], [
                                'source' => 'active_query',
                                'cancelled_invoice' => true,
                            ]),
                        ])->save();

                        return ['dispatch' => false, 'invoice' => $invoice, 'payment_no' => (string) $lockedPayment->payment_no];
                    }

                    // 正常未支付 → 完成入账
                    throw_if(
                        ! in_array((int) $invoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE], true),
                        new BusinessException('账单状态异常，无法处理支付')
                    );

                    $lockedPayment->forceFill([
                        'trade_no' => $tradeNo,
                        'status' => PaymentStatus::SUCCESS,
                        'callback_raw' => array_merge($queryResult['raw'] ?? [], [
                            'source' => 'active_query',
                            'trade_status' => $tradeStatus,
                        ]),
                        'paid_at' => now(),
                    ])->save();

                    $invoice->forceFill([
                        'status' => InvoiceStatus::PAID,
                        'paid_amount' => $invoice->amount,
                        'paid_at' => now(),
                    ])->save();

                    $invoice->order?->forceFill([
                        'status' => OrderStatus::PAID,
                        'paid_amount' => $lockedPayment->amount,
                        'paid_at' => now(),
                    ])->save();

                    $this->closeOtherPendingPayments($invoice, (int) $lockedPayment->id, 'invoice_paid_by_alipay_query');

                    return [
                        'dispatch' => true,
                        'invoice' => $invoice,
                        'payment_no' => (string) $lockedPayment->payment_no,
                    ];
                });
            }, '支付处理中，请稍后重试');
        } catch (\Throwable $exception) {
            Log::error('[支付宝主动查询] 入账处理异常', [
                'payment_no' => $payment->payment_no,
                'trade_no' => $tradeNo,
                'message' => $exception->getMessage(),
            ]);
            return;
        }

        if (($result['dispatch'] ?? false) && ($result['invoice'] ?? null) instanceof Invoice) {
            $this->handlePaidInvoice($result['invoice'], 'alipay_query:' . ($result['payment_no'] ?? $payment->payment_no));
        }
    }

    /**
     * 后台发起订单退款
     */
    public function refundOrder(Order $order, array $payload = [], array $context = []): array
    {
        $refundMethod = trim((string) ($payload['refund_method'] ?? 'original'));
        $paymentGateway = $this->detectPrimaryPaymentGateway($order);

        return match ($refundMethod) {
            'balance' => $this->refundOrderToBalance($order, $payload, $context, 'balance', '退回余额'),
            'original' => match ($paymentGateway) {
                'alipay' => $this->refundOrderByAlipay($order, $payload, $context),
                'balance' => $this->refundOrderToBalance($order, $payload, $context, 'original', '原路退款'),
                default => throw new BusinessException('当前支付方式不支持原路退款'),
            },
            default => throw new BusinessException('不支持的退款方式'),
        };
    }

    /**
     * 后台发起支付宝原路退款
     */
    public function refundOrderByAlipay(Order $order, array $payload = [], array $context = []): array
    {
        $lockKey = "lock:refund:alipay:order:{$order->id}";

        return $this->withLock($lockKey, 40, function () use ($order, $payload, $context) {
            $snapshot = DB::transaction(function () use ($order, $payload) {
                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->with(['invoice.payments', 'service'])
                    ->findOrFail($order->id);

                $invoice = $lockedOrder->invoice;
                throw_if(! $invoice instanceof Invoice, new BusinessException('订单未关联账单，无法退款'));

                if ((int) $lockedOrder->status === OrderStatus::REFUNDED) {
                    return [
                        'already_refunded' => true,
                        'order_id' => (int) $lockedOrder->id,
                        'payment_id' => 0,
                    ];
                }

                throw_if((int) $invoice->status !== InvoiceStatus::PAID, new BusinessException('当前账单状态不支持支付宝退款'));
                throw_if(
                    ! in_array((int) $lockedOrder->status, [OrderStatus::PAID, OrderStatus::PROCESSING, OrderStatus::COMPLETED], true),
                    new BusinessException('当前订单状态不支持支付宝退款')
                );

                $payment = $this->resolveRefundableAlipayPayment($invoice);
                throw_if(! $payment instanceof Payment, new BusinessException('未找到可退款的支付宝支付记录'));

                if ((int) $payment->status === PaymentStatus::REFUNDED) {
                    return [
                        'already_refunded' => true,
                        'order_id' => (int) $lockedOrder->id,
                        'payment_id' => (int) $payment->id,
                    ];
                }

                $refundAmount = round((float) ($payload['amount'] ?? $payment->amount), 2);
                $paymentAmount = round((float) $payment->amount, 2);
                throw_if($refundAmount <= 0, new BusinessException('退款金额不正确'));
                throw_if(abs($refundAmount - $paymentAmount) > 0.00001, new BusinessException('当前仅支持按原支付金额全额退款'));

                $refundReason = trim((string) ($payload['remark'] ?? ''));
                if ($refundReason === '') {
                    $refundReason = '后台发起支付宝原路退款';
                }

                return [
                    'already_refunded' => false,
                    'order_id' => (int) $lockedOrder->id,
                    'payment_id' => (int) $payment->id,
                    'payment_no' => (string) $payment->payment_no,
                    'trade_no' => trim((string) ($payment->trade_no ?? '')),
                    'refund_amount' => $refundAmount,
                    'refund_reason' => $refundReason,
                    'out_request_no' => $this->buildAlipayRefundRequestNo($payment),
                ];
            });

            if (($snapshot['already_refunded'] ?? false) === true) {
                return $snapshot;
            }

            $refundResult = $this->alipayService->refund(
                outTradeNo: (string) $snapshot['payment_no'],
                refundAmount: (float) $snapshot['refund_amount'],
                refundReason: (string) $snapshot['refund_reason'],
                tradeNo: (string) ($snapshot['trade_no'] ?? ''),
                outRequestNo: (string) $snapshot['out_request_no'],
            );

            return DB::transaction(function () use ($snapshot, $refundResult, $context) {
                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->with('invoice')
                    ->findOrFail((int) $snapshot['order_id']);
                $payment = Payment::query()
                    ->lockForUpdate()
                    ->findOrFail((int) $snapshot['payment_id']);

                if ((int) $lockedOrder->status === OrderStatus::REFUNDED || (int) $payment->status === PaymentStatus::REFUNDED) {
                    if ((int) $lockedOrder->status !== OrderStatus::REFUNDED) {
                        $lockedOrder->forceFill(['status' => OrderStatus::REFUNDED])->save();
                    }

                    return [
                        'already_refunded' => true,
                        'order_id' => (int) $lockedOrder->id,
                        'payment_id' => (int) $payment->id,
                        'refund' => (array) data_get((array) ($payment->callback_raw ?? []), 'refund', []),
                    ];
                }

                $refundRecord = [
                    'out_request_no' => (string) $snapshot['out_request_no'],
                    'refund_amount' => number_format((float) $snapshot['refund_amount'], 2, '.', ''),
                    'refund_reason' => (string) $snapshot['refund_reason'],
                    'trade_no' => (string) ($refundResult['trade_no'] ?? $payment->trade_no ?? ''),
                    'refund_fee' => number_format((float) ($refundResult['refund_fee'] ?? $snapshot['refund_amount']), 2, '.', ''),
                    'fund_change' => (string) ($refundResult['fund_change'] ?? ''),
                    'gmt_refund_pay' => (string) ($refundResult['gmt_refund_pay'] ?? ''),
                    'operator_id' => (int) ($context['operator_id'] ?? 0),
                    'operator_name' => (string) ($context['operator_name'] ?? ''),
                    'trace_id' => (string) ($context['trace_id'] ?? ''),
                    'refunded_at' => now()->format('Y-m-d H:i:s'),
                    'raw' => (array) ($refundResult['raw'] ?? []),
                ];

                $callbackRaw = (array) ($payment->callback_raw ?? []);
                $callbackRaw['refund'] = $refundRecord;

                $payment->forceFill([
                    'status' => PaymentStatus::REFUNDED,
                    'callback_raw' => $callbackRaw,
                ])->save();

                $lockedOrder->forceFill([
                    'status' => OrderStatus::REFUNDED,
                ])->save();
                $this->restoreOrderProductStockIfNeeded($lockedOrder);

                Log::info('[支付宝退款] 订单退款成功', [
                    'order_id' => $lockedOrder->id,
                    'payment_id' => $payment->id,
                    'payment_no' => $payment->payment_no,
                    'refund_amount' => $refundRecord['refund_amount'],
                    'out_request_no' => $refundRecord['out_request_no'],
                ]);

                return [
                    'already_refunded' => false,
                    'order_id' => (int) $lockedOrder->id,
                    'payment_id' => (int) $payment->id,
                    'refund' => $refundRecord,
                ];
            });
        }, '退款处理中，请勿重复提交');
    }

    /**
     * 后台发起账单退款到用户余额
     */
    public function refundInvoiceToBalance(User $user, Invoice $invoice, array $payload = [], array $context = []): array
    {
        $lockKey = "lock:refund:balance:invoice:{$invoice->id}";

        return $this->withLock($lockKey, 40, function () use ($user, $invoice, $payload, $context) {
            return DB::transaction(function () use ($user, $invoice, $payload, $context) {
                $lockedInvoice = Invoice::query()
                    ->lockForUpdate()
                    ->with(['order', 'payments'])
                    ->findOrFail($invoice->id);
                $lockedUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail($user->id);
                $lockedAccount = $this->lockUserAccount($lockedUser->id);

                throw_if((int) $lockedInvoice->user_id !== (int) $lockedUser->id, new BusinessException('账单与用户不匹配'));
                throw_if((int) $lockedInvoice->status !== InvoiceStatus::PAID, new BusinessException('当前账单状态不支持退款'));

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
                    $order && ! in_array((int) $order->status, [OrderStatus::PAID, OrderStatus::PROCESSING, OrderStatus::COMPLETED], true),
                    new BusinessException('当前订单状态不支持退款')
                );

                $payment = $this->resolvePrimaryRefundablePayment($lockedInvoice);
                throw_if(! $payment instanceof Payment, new BusinessException('未找到可退款的支付记录'));

                if ((int) $payment->status === PaymentStatus::REFUNDED) {
                    return [
                        'already_refunded' => true,
                        'invoice_id' => (int) $lockedInvoice->id,
                        'payment_id' => (int) $payment->id,
                        'refund' => (array) data_get((array) ($payment->callback_raw ?? []), 'refund', []),
                    ];
                }

                $refundAmount = round((float) ($payload['amount'] ?? $payment->amount), 2);
                $paymentAmount = round((float) $payment->amount, 2);
                throw_if($refundAmount <= 0, new BusinessException('退款金额不正确'));
                throw_if(abs($refundAmount - $paymentAmount) > 0.00001, new BusinessException('当前仅支持按原支付金额全额退款'));

                $refundReason = trim((string) ($payload['remark'] ?? ''));
                if ($refundReason === '') {
                    $refundReason = '后台退回用户余额';
                }

                $refundMethod = trim((string) ($payload['refund_method'] ?? 'balance')) ?: 'balance';
                $refundMethodLabel = trim((string) ($payload['refund_method_label'] ?? ''));

                if ($refundMethodLabel === '') {
                    $refundMethodLabel = $refundMethod === 'original' ? '原路退款' : '退回余额';
                }

                $lockedAccount->forceFill([
                    'cash_balance' => round((float) $lockedAccount->cash_balance + $refundAmount, 2),
                ])->save();

                AccountTransaction::query()->create([
                    'user_id'      => $lockedUser->id,
                    'account_type' => 'cash',
                    'event_type'   => 'refund',
                    'change_amount' => $refundAmount,
                    'balance_after' => $lockedAccount->cash_balance,
                    'source_type'  => 'invoice',
                    'source_id'    => $lockedInvoice->id,
                    'origin_type'  => 'invoice_refund',
                    'origin_id'    => $lockedInvoice->id,
                    'remark'       => "账单退款 {$lockedInvoice->invoice_no}",
                ]);

                $refundRecord = [
                    'refund_method' => $refundMethod,
                    'refund_method_label' => $refundMethodLabel,
                    'refund_amount' => number_format($refundAmount, 2, '.', ''),
                    'refund_reason' => $refundReason,
                    'trade_no' => (string) ($payment->trade_no ?? ''),
                    'original_gateway' => (string) $payment->gateway,
                    'original_gateway_label' => $this->resolvePaymentGatewayLabel((string) $payment->gateway),
                    'operator_id' => (int) ($context['operator_id'] ?? 0),
                    'operator_name' => (string) ($context['operator_name'] ?? ''),
                    'trace_id' => (string) ($context['trace_id'] ?? ''),
                    'refunded_at' => now()->format('Y-m-d H:i:s'),
                ];

                $callbackRaw = (array) ($payment->callback_raw ?? []);
                $callbackRaw['refund'] = $refundRecord;

                $payment->forceFill([
                    'status' => PaymentStatus::REFUNDED,
                    'callback_raw' => $callbackRaw,
                ])->save();

                if ($order) {
                    $order->forceFill([
                        'status' => OrderStatus::REFUNDED,
                    ])->save();

                    $this->restoreOrderProductStockIfNeeded($order);
                }

                Log::info('[账单退款] 已退回用户余额', [
                    'invoice_id' => $lockedInvoice->id,
                    'invoice_no' => $lockedInvoice->invoice_no,
                    'payment_id' => $payment->id,
                    'refund_amount' => $refundRecord['refund_amount'],
                    'user_id' => $lockedUser->id,
                ]);

                return [
                    'already_refunded' => false,
                    'invoice_id' => (int) $lockedInvoice->id,
                    'payment_id' => (int) $payment->id,
                    'refund' => $refundRecord,
                ];
            });
        }, '退款处理中，请勿重复提交');
    }

    private function refundOrderToBalance(
        Order $order,
        array $payload,
        array $context,
        string $refundMethod,
        string $refundMethodLabel,
    ): array {
        $order->loadMissing(['invoice', 'user']);

        throw_if(! $order->invoice instanceof Invoice, new BusinessException('订单未关联账单，无法退款'));
        throw_if(! $order->user instanceof User, new BusinessException('订单未关联用户，无法退款'));

        return $this->refundInvoiceToBalance($order->user, $order->invoice, array_merge($payload, [
            'refund_method' => $refundMethod,
            'refund_method_label' => $refundMethodLabel,
        ]), $context);
    }

    public function handlePaidInvoice(Invoice $invoice, ?string $traceId = null): void
    {
        $invoice = $invoice->fresh(['order']) ?? $invoice;

        if (! $invoice->order || (int) $invoice->status !== InvoiceStatus::PAID) {
            return;
        }

        $this->adminOrderNotificationService->notifyOrderPaid($invoice->order);
        $this->paidOrderBusinessFlowDispatcher->dispatchPaidInvoice($invoice, $traceId);
    }

    public function processPaidOrderReferralRewardById(int $orderId, ?string $traceId = null): void
    {
        $order = $this->loadPayableOrderForBusinessFlow($orderId);

        if (! $order) {
            return;
        }

        try {
            $this->referralService->rewardForPaidOrder($order, $traceId);
        } catch (\Throwable $exception) {
            Log::error('[支付后推荐奖励] 处理失败', [
                'order_id' => $order->id,
                'order_no' => $order->order_no,
                'trace_id' => $traceId,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
    }

    public function processPaidOrderFulfillmentById(int $orderId): void
    {
        $order = $this->loadPayableOrderForBusinessFlow($orderId);

        if (! $order || ! $order->invoice) {
            return;
        }

        $this->provisionPaidOrder($order->invoice);
    }

    private function provisionPaidOrder(?Invoice $invoice): void
    {
        $order = $invoice?->order;

        if (! $order || (int) $invoice->status !== InvoiceStatus::PAID) {
            return;
        }

        try {
            if ($order->type === 'renew') {
                $this->serviceRenewService->processPaidRenewOrder($order);
                return;
            }

            $this->provisionService->processPaidOrder($order);
        } catch (\Throwable $exception) {
            Log::error('[支付后自动开通] 调用开通服务失败', [
                'invoice_id' => $invoice->id,
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
    }

    private function loadPayableOrderForBusinessFlow(int $orderId): ?Order
    {
        $order = Order::query()
            ->with(['invoice', 'user.referralProfile', 'product.supplier', 'service'])
            ->find($orderId);

        if (! $order || ! $order->invoice) {
            return null;
        }

        if ((int) $order->invoice->status !== InvoiceStatus::PAID) {
            return null;
        }

        if (! in_array((int) $order->status, [OrderStatus::PAID, OrderStatus::PROCESSING, OrderStatus::COMPLETED], true)) {
            return null;
        }

        return $order;
    }

    private function closeOtherPendingPayments(Invoice $invoice, int $excludePaymentId, string $reason): void
    {
        $pendingPayments = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentStatus::PENDING)
            ->where('id', '!=', $excludePaymentId)
            ->lockForUpdate()
            ->get();

        foreach ($pendingPayments as $pendingPayment) {
            $callbackRaw = (array) ($pendingPayment->callback_raw ?? []);
            $callbackRaw['closed_reason'] = $reason;

            $pendingPayment->forceFill([
                'status' => PaymentStatus::FAILED,
                'callback_raw' => $callbackRaw,
            ])->save();
        }
    }

    private function resolveRefundableAlipayPayment(Invoice $invoice): ?Payment
    {
        return $this->resolvePrimaryRefundablePayment($invoice, ['alipay']);
    }

    /**
     * 当前仅支持单次全额退款，使用固定退款单号保证后台重复点击时幂等。
     */
    private function buildAlipayRefundRequestNo(Payment $payment): string
    {
        return 'RFD' . $payment->payment_no;
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
                ->filter(fn (Payment $payment) => in_array((string) $payment->gateway, $gateways, true))
                ->values();
        }

        return $payments
            ->first(fn (Payment $payment) => ! (bool) data_get((array) ($payment->callback_raw ?? []), 'duplicate_paid', false)
                && in_array((int) $payment->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true))
            ?? $payments->first(fn (Payment $payment) => in_array((int) $payment->status, [PaymentStatus::SUCCESS, PaymentStatus::REFUNDED], true));
    }

    private function detectPrimaryPaymentGateway(Order $order): string
    {
        $order->loadMissing(['invoice.payments']);
        $payment = $order->invoice instanceof Invoice
            ? $this->resolvePrimaryRefundablePayment($order->invoice)
            : null;

        return (string) ($payment?->gateway ?? '');
    }

    private function restoreOrderProductStockIfNeeded(Order $order): void
    {
        if ((string) $order->type !== 'new' || (int) ($order->service_id ?? 0) > 0 || ! $order->product_id) {
            return;
        }

        $product = Product::query()
            ->lockForUpdate()
            ->find($order->product_id);

        if ($product instanceof Product && (int) $product->stock >= 0) {
            $product->increment('stock', max((int) ($order->quantity ?? 1), 1));
        }
    }

    private function resolvePaymentGatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            'alipay' => '支付宝支付',
            'wechat' => '微信支付',
            'balance' => '余额支付',
            'bank_transfer' => '银行转账',
            'offline' => '线下支付',
            default => '手动入账',
        };
    }

    private function lockUserAccount(int $userId): UserAccount
    {
        $account = UserAccount::query()->lockForUpdate()->find($userId);
        if ($account) {
            return $account;
        }

        UserAccount::query()->create([
            'user_id' => $userId,
            'cash_balance' => '0.00',
            'credit_limit' => '0.00',
            'referral_frozen_balance' => '0.00',
            'referral_available_balance' => '0.00',
            'referral_pending_withdrawal_balance' => '0.00',
            'referral_withdrawn_balance' => '0.00',
            'version' => 0,
        ]);

        return UserAccount::query()->lockForUpdate()->findOrFail($userId);
    }

    private function withLock(string $lockKey, int $seconds, callable $callback, string $timeoutMessage): mixed
    {
        try {
            return Cache::lock($lockKey, $seconds)->block(5, $callback);
        } catch (LockTimeoutException) {
            throw new BusinessException($timeoutMessage);
        }
    }
}
