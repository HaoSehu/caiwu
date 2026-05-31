<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\FinanceLedgerEventType;
use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\BalanceLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\ClientServiceConsole\ServiceTrafficPackageService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\PaymentGateway\AlipayFaceToFaceService;
use App\Services\Provisioning\ProvisionService;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\Referral\ReferralService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PaymentService
{
    public function __construct(
        private ProvisionService $provisionService,
        private AlipayFaceToFaceService $alipayService,
        private ServiceRenewService $serviceRenewService,
        private ReferralService $referralService,
        private PaidOrderBusinessFlowDispatcher $paidOrderBusinessFlowDispatcher,
        private AdminOrderNotificationService $adminOrderNotificationService,
        private CouponService $couponService,
        private InvoiceService $invoiceService,
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

                throw_if(
                    ! in_array((int) $lockedInvoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE], true),
                    new BusinessException('账单状态异常，无法支付')
                );

                $amount = round((float) $lockedInvoice->amount - (float) ($lockedInvoice->paid_amount ?? 0), 2);
                $currentBalance = $this->getUserBalance($lockedUser);
                throw_if($amount <= 0, new BusinessException('当前账单无需支付'));
                throw_if($currentBalance < $amount, new BusinessException('余额不足'));

                $balanceAfter = $this->setUserBalance($lockedUser, $currentBalance - $amount);
                $this->createBalanceLog(
                    (int) $lockedUser->id,
                    FinanceLedgerEventType::INVOICE_PAYMENT,
                    -$amount,
                    $balanceAfter,
                    (int) $lockedInvoice->id,
                    '支付账单 '.(string) $lockedInvoice->invoice_no
                );

                $payment = Payment::query()->create([
                    'payment_no' => Payment::generatePaymentNo(),
                    'user_id' => $lockedUser->id,
                    'order_id' => (int) ($lockedInvoice->order?->id ?? 0) ?: null,
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
                $this->syncProjection($payment);

                $lockedInvoice->forceFill([
                    'status' => InvoiceStatus::PAID,
                    'paid_amount' => $lockedInvoice->amount,
                    'paid_at' => now(),
                ])->save();

                // 保持 order 状态同步（orders 作为内部基础设施仍存在）
                $lockedInvoice->order?->forceFill([
                    'status' => OrderStatus::PAID,
                    'paid_amount' => $amount,
                    'paid_at' => now(),
                ])->save();

                $this->closeOtherPendingPayments($lockedInvoice, (int) $payment->id, 'invoice_paid_by_balance');

                return $payment;
            });
        }, '支付请求处理中，请勿重复提交');

        $this->handlePaidInvoice($invoice, $traceId !== '' ? 'balance:'.$traceId : 'balance:'.$invoice->id);

        return $payment->fresh() ?? $payment;
    }

    public function payOrderByBalance(Order $order, User $user, array $context = []): Payment
    {
        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $lockKey = "lock:pay:order:{$order->id}";

        $payment = $this->withLock($lockKey, 30, function () use ($order, $user, $traceId) {
            return DB::transaction(function () use ($order, $user, $traceId) {
                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->with('invoice')
                    ->findOrFail($order->id);
                $lockedUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail($user->id);

                throw_if(
                    (int) $lockedOrder->status !== OrderStatus::PENDING,
                    new BusinessException('当前订单状态不支持支付')
                );

                $amount = $this->resolveOrderPayableAmount($lockedOrder);
                if ($amount > 0) {
                    $currentBalance = $this->getUserBalance($lockedUser);
                    throw_if($currentBalance < $amount, new BusinessException('余额不足'));

                    $balanceAfter = $this->setUserBalance($lockedUser, $currentBalance - $amount);
                    $this->createBalanceLog(
                        (int) $lockedUser->id,
                        FinanceLedgerEventType::INVOICE_PAYMENT,
                        -$amount,
                        $balanceAfter,
                        (int) $lockedOrder->id,
                        '支付订单 '.(string) $lockedOrder->order_no
                    );
                }

                $payment = Payment::query()->create([
                    'payment_no' => Payment::generatePaymentNo(),
                    'user_id' => $lockedUser->id,
                    'order_id' => (int) $lockedOrder->id,
                    'invoice_id' => (int) ($lockedOrder->invoice?->id ?? 0) ?: null,
                    'gateway' => $amount > 0 ? 'balance' : 'free',
                    'amount' => $amount,
                    'status' => PaymentStatus::SUCCESS,
                    'callback_raw' => [
                        'source' => $amount > 0 ? 'balance' : 'free_confirm',
                        'trace_id' => $traceId,
                    ],
                    'paid_at' => now(),
                ]);
                $this->syncProjection($payment);

                $lockedOrder->forceFill([
                    'status' => OrderStatus::PAID,
                    'paid_amount' => $amount,
                    'paid_at' => now(),
                ])->save();

                if ($lockedOrder->invoice instanceof Invoice) {
                    $lockedOrder->invoice->forceFill([
                        'status' => InvoiceStatus::PAID,
                        'paid_amount' => $lockedOrder->invoice->amount,
                        'paid_at' => now(),
                    ])->save();
                }

                $this->closeOtherPendingPaymentsForOrder($lockedOrder, (int) $payment->id, 'order_paid_by_balance');

                return $payment;
            });
        }, '支付请求处理中，请勿重复提交');

        $order = $order->fresh(['invoice']) ?? $order;
        if ($order->invoice instanceof Invoice) {
            $this->handlePaidInvoice($order->invoice, $traceId !== '' ? 'balance:'.$traceId : 'balance:'.$order->id);
        }

        return $payment->fresh() ?? $payment;
    }

    /**
     * 资金调整（管理员手动，正数增加、负数扣减）
     */
    public function adjustBalance(User $user, float $amount, string $remark = '管理员手动调整', array $context = []): void
    {
        throw_if($amount == 0, new BusinessException('调整金额不能为 0'));

        DB::transaction(function () use ($user, $amount, $remark, $context) {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            $currentBalance = $this->getUserBalance($lockedUser);
            $newBalance = $currentBalance + $amount;

            throw_if($newBalance < 0, new BusinessException(
                '扣减后余额不足，当前余额 ¥'.number_format($currentBalance, 2).'，扣减 ¥'.number_format(abs($amount), 2)
            ));

            $balanceAfter = $this->setUserBalance($lockedUser, $newBalance);

            $eventType = $amount > 0
                ? FinanceLedgerEventType::MANUAL_RECHARGE
                : FinanceLedgerEventType::MANUAL_DEDUCTION;
            $this->createBalanceLog(
                (int) $lockedUser->id,
                $eventType,
                $amount,
                $balanceAfter,
                (int) $lockedUser->id,
                $remark,
                [
                    'operator' => trim((string) ($context['operator_name'] ?? '')),
                    'trace_id' => trim((string) ($context['trace_id'] ?? '')),
                ]
            );
        });

        try {
            if ($amount > 0) {
                $this->invoiceService->createForRecharge($user, abs($amount));
            } else {
                $this->invoiceService->createForDeduction($user, abs($amount), $remark);
            }
        } catch (\Throwable $e) {
            Log::warning('[资金调整] 创建账单失败', [
                'user_id' => $user->id,
                'amount' => $amount,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 充值（管理员手动）—— 兼容旧调用
     */
    public function recharge(User $user, float $amount, string $remark = '手动充值'): void
    {
        $this->adjustBalance($user, abs($amount), $remark);
    }

    /**
     * 支付宝充值 — 预下单
     */
    public function rechargeByAlipay(User $user, float $amount): array
    {
        $this->assertVerifiedUser($user);

        throw_if(
            ! $this->alipayService->isEnabled(),
            new BusinessException('支付宝支付未启用')
        );
        throw_if($amount < 1, new BusinessException('充值金额不能小于 1 元'));
        throw_if($amount > 50000, new BusinessException('单笔充值不能超过 50000 元'));

        $normalizedAmount = round($amount, 2);
        $lockKey = 'lock:recharge:create:'.$user->id.':'.md5(number_format($normalizedAmount, 2, '.', ''));

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
                    'user_id' => $user->id,
                    'invoice_id' => null,
                    'gateway' => 'alipay',
                    'amount' => $normalizedAmount,
                    'status' => PaymentStatus::PENDING,
                ]);
            });
        }, '充值请求处理中，请勿重复提交');

        $subject = config('app.name', 'IDC').' - 账户充值 ¥'.number_format($normalizedAmount, 2, '.', '');
        $result = $this->alipayService->precreate($payment->payment_no, $normalizedAmount, $subject);

        $payment->forceFill([
            'callback_raw' => array_merge((array) ($payment->callback_raw ?? []), [
                'source' => 'alipay_recharge_precreate',
            ]),
        ])->save();
        $this->syncProjection($payment);

        return [
            'payment_no' => $payment->payment_no,
            'qr_code' => $result['qr_code'],
            'amount' => number_format($normalizedAmount, 2, '.', ''),
        ];
    }

    private function assertVerifiedUser(User $user): void
    {
        throw_if(
            (int) $user->is_verified !== 1,
            new BusinessException('请先完成实名认证后再继续操作', 40301)
        );
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

            $completedUser = null;

            DB::transaction(function () use ($payment, $tradeNo, $raw, &$completedUser) {
                $payment->update([
                    'trade_no' => $tradeNo,
                    'status' => PaymentStatus::SUCCESS,
                    'callback_raw' => $raw,
                    'paid_at' => now(),
                ]);
                $this->syncProjection($payment);

                $user = User::query()->lockForUpdate()->findOrFail($payment->user_id);
                $balanceAfter = $this->setUserBalance($user, $this->getUserBalance($user) + (float) $payment->amount);

                $this->createBalanceLog(
                    (int) $user->id,
                    FinanceLedgerEventType::RECHARGE,
                    (float) $payment->amount,
                    $balanceAfter,
                    (int) $payment->id,
                    '支付宝充值 '.(string) $payment->payment_no
                );

                $completedUser = $user;
            });

            if ($completedUser) {
                try {
                    $this->invoiceService->createForRecharge($completedUser, (float) $payment->amount, $payment);
                } catch (\Throwable $e) {
                    Log::warning('[充值] 创建充值账单失败', [
                        'payment_id' => $payment->id,
                        'user_id' => $completedUser->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * 支付宝当面付 — 预下单，返回二维码
     */
    public function payByAlipay(Invoice $invoice, User $user, array $context = []): array
    {
        throw_if(
            ! $this->alipayService->isEnabled(),
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
                        'order_id' => (int) ($lockedInvoice->order?->id ?? 0) ?: null,
                        'invoice_id' => $lockedInvoice->id,
                        'gateway' => 'alipay',
                        'amount' => $amount,
                        'status' => PaymentStatus::PENDING,
                        'callback_raw' => [
                            'source' => 'alipay_precreate',
                            'trace_id' => $traceId,
                        ],
                    ]);
                    $this->syncProjection($payment);
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

        $subject = config('app.name', 'IDC').' - 账单 '.$lockedInvoice->invoice_no;
        $result = $this->alipayService->precreate($payment->payment_no, (float) $payment->amount, $subject);

        return [
            'payment_no' => $payment->payment_no,
            'qr_code' => $result['qr_code'],
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
        ];
    }

    /**
     * 先扣余额，再为剩余金额生成支付宝二维码。
     */
    public function payByBalanceAndAlipay(Invoice $invoice, User $user, float $balanceAmount, array $context = []): array
    {
        throw_if(
            ! $this->alipayService->isEnabled(),
            new BusinessException('支付宝支付未启用')
        );

        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $lockKey = "lock:pay:mix:invoice:{$invoice->id}";
        $normalizedBalanceAmount = round(max($balanceAmount, 0), 2);

        $payload = $this->withLock($lockKey, 20, function () use ($invoice, $user, $traceId, $normalizedBalanceAmount) {
            return DB::transaction(function () use ($invoice, $user, $traceId, $normalizedBalanceAmount) {
                $lockedInvoice = Invoice::query()
                    ->lockForUpdate()
                    ->with('order')
                    ->findOrFail($invoice->id);
                $lockedUser = User::query()
                    ->lockForUpdate()
                    ->findOrFail($user->id);

                throw_if(
                    ! in_array((int) $lockedInvoice->status, [InvoiceStatus::UNPAID, InvoiceStatus::OVERDUE], true),
                    new BusinessException('账单状态异常，无法支付')
                );

                $remainingAmount = round((float) $lockedInvoice->amount - (float) ($lockedInvoice->paid_amount ?? 0), 2);
                throw_if($remainingAmount <= 0, new BusinessException('当前账单无需支付'));
                throw_if($normalizedBalanceAmount <= 0, new BusinessException('余额支付金额必须大于 0'));
                throw_if($normalizedBalanceAmount >= $remainingAmount, new BusinessException('余额支付金额需小于待支付金额'));

                $currentBalance = $this->getUserBalance($lockedUser);
                throw_if($currentBalance < $normalizedBalanceAmount, new BusinessException('余额不足'));

                $balanceAfter = $this->setUserBalance($lockedUser, $currentBalance - $normalizedBalanceAmount);
                $this->createBalanceLog(
                    (int) $lockedUser->id,
                    FinanceLedgerEventType::INVOICE_PAYMENT,
                    -$normalizedBalanceAmount,
                    $balanceAfter,
                    (int) $lockedInvoice->id,
                    '账单余额支付 '.(string) $lockedInvoice->invoice_no
                );

                $payment = Payment::query()->create([
                    'payment_no' => Payment::generatePaymentNo(),
                    'user_id' => $lockedUser->id,
                    'order_id' => (int) ($lockedInvoice->order?->id ?? 0) ?: null,
                    'invoice_id' => $lockedInvoice->id,
                    'gateway' => 'balance',
                    'amount' => $normalizedBalanceAmount,
                    'status' => PaymentStatus::SUCCESS,
                    'callback_raw' => [
                        'source' => 'balance_part',
                        'trace_id' => $traceId,
                        'mix_payment' => true,
                    ],
                    'paid_at' => now(),
                ]);
                $this->syncProjection($payment);

                $nextPaidAmount = round((float) ($lockedInvoice->paid_amount ?? 0) + $normalizedBalanceAmount, 2);
                $lockedInvoice->forceFill([
                    'paid_amount' => $nextPaidAmount,
                ])->save();

                return [
                    'invoice' => $lockedInvoice,
                    'payment' => $payment,
                    'remaining_amount' => round(max((float) $lockedInvoice->amount - $nextPaidAmount, 0), 2),
                ];
            });
        }, '支付请求处理中，请勿重复提交');

        /** @var Invoice $lockedInvoice */
        $lockedInvoice = $payload['invoice'];
        /** @var Payment $balancePayment */
        $balancePayment = $payload['payment'];
        $remainingAmount = (float) ($payload['remaining_amount'] ?? 0);

        throw_if($remainingAmount <= 0, new BusinessException('当前账单无需继续发起支付宝支付'));

        $alipayPayment = DB::transaction(function () use ($lockedInvoice, $user, $remainingAmount, $traceId, $balancePayment) {
            $existingPayment = Payment::query()
                ->where('invoice_id', $lockedInvoice->id)
                ->where('gateway', 'alipay')
                ->where('status', PaymentStatus::PENDING)
                ->where('amount', $remainingAmount)
                ->latest('id')
                ->first();

            if ($existingPayment) {
                return $existingPayment;
            }

            $payment = Payment::query()->create([
                'payment_no' => Payment::generatePaymentNo(),
                'user_id' => $user->id,
                'order_id' => (int) ($lockedInvoice->order?->id ?? 0) ?: null,
                'invoice_id' => $lockedInvoice->id,
                'gateway' => 'alipay',
                'amount' => $remainingAmount,
                'status' => PaymentStatus::PENDING,
                'callback_raw' => [
                    'source' => 'alipay_precreate_mix',
                    'trace_id' => $traceId,
                    'mix_payment' => true,
                    'balance_payment_no' => (string) $balancePayment->payment_no,
                ],
            ]);
            $this->syncProjection($payment);

            return $payment;
        });

        $subject = config('app.name', 'IDC').' - 账单 '.$lockedInvoice->invoice_no;
        $result = $this->alipayService->precreate($alipayPayment->payment_no, $remainingAmount, $subject);

        return [
            'balance_payment_no' => $balancePayment->payment_no,
            'balance_amount' => number_format((float) $balancePayment->amount, 2, '.', ''),
            'payment_no' => $alipayPayment->payment_no,
            'qr_code' => $result['qr_code'],
            'amount' => number_format($remainingAmount, 2, '.', ''),
            'paid_amount' => number_format((float) $lockedInvoice->paid_amount, 2, '.', ''),
            'payable_amount' => number_format($remainingAmount, 2, '.', ''),
        ];
    }

    public function payOrderByAlipay(Order $order, User $user, array $context = []): array
    {
        throw_if(
            ! $this->alipayService->isEnabled(),
            new BusinessException('支付宝支付未启用')
        );

        $traceId = trim((string) ($context['trace_id'] ?? ''));
        $lockKey = "lock:pay:alipay:order:{$order->id}";

        $payload = $this->withLock($lockKey, 20, function () use ($order, $user, $traceId) {
            return DB::transaction(function () use ($order, $user, $traceId) {
                $lockedOrder = Order::query()
                    ->lockForUpdate()
                    ->with('invoice')
                    ->findOrFail($order->id);

                throw_if(
                    (int) $lockedOrder->status !== OrderStatus::PENDING,
                    new BusinessException('当前订单状态不支持支付')
                );

                $amount = $this->resolveOrderPayableAmount($lockedOrder);
                throw_if($amount <= 0, new BusinessException('当前订单无需支付'));

                $payment = Payment::query()
                    ->where('order_id', $lockedOrder->id)
                    ->where('gateway', 'alipay')
                    ->where('status', PaymentStatus::PENDING)
                    ->latest('id')
                    ->first();

                if (! $payment) {
                    $payment = Payment::query()->create([
                        'payment_no' => Payment::generatePaymentNo(),
                        'user_id' => $user->id,
                        'order_id' => (int) $lockedOrder->id,
                        'invoice_id' => (int) ($lockedOrder->invoice?->id ?? 0) ?: null,
                        'gateway' => 'alipay',
                        'amount' => $amount,
                        'status' => PaymentStatus::PENDING,
                        'callback_raw' => [
                            'source' => 'alipay_precreate',
                            'trace_id' => $traceId,
                        ],
                    ]);
                    $this->syncProjection($payment);
                }

                return [
                    'order' => $lockedOrder,
                    'payment' => $payment,
                ];
            });
        }, '支付二维码生成中，请稍后重试');

        /** @var Order $lockedOrder */
        $lockedOrder = $payload['order'];
        /** @var Payment $payment */
        $payment = $payload['payment'];

        $subject = config('app.name', 'IDC').' - 订单 '.$lockedOrder->order_no;
        $result = $this->alipayService->precreate($payment->payment_no, (float) $payment->amount, $subject);

        return [
            'payment_no' => $payment->payment_no,
            'qr_code' => $result['qr_code'],
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
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

        $paymentNo = $params['out_trade_no'] ?? '';
        $tradeStatus = $params['trade_status'] ?? '';
        $tradeNo = $params['trade_no'] ?? '';

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

        $lockKey = "lock:alipay:payment:{$payment->id}";

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
                        $this->syncProjection($lockedPayment);

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
                        $this->syncProjection($lockedPayment);

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
                    $this->syncProjection($lockedPayment);

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
            $this->handlePaidInvoice($result['invoice'], 'alipay:'.($result['payment_no'] ?? $payment->payment_no));
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

        // 金额校验（total_amount 缺失或为零均视为异常，拒绝入账）
        $queryAmount = round((float) ($queryResult['total_amount'] ?? 0), 2);
        $expectedAmount = round((float) $payment->amount, 2);
        if ($queryAmount <= 0 || abs($queryAmount - $expectedAmount) > 0.0001) {
            Log::warning('[支付宝主动查询] 金额校验失败', [
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

        $lockKey = "lock:alipay:payment:{$payment->id}";

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
                        $this->syncProjection($lockedPayment);

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
                        $this->syncProjection($lockedPayment);

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
                    $this->syncProjection($lockedPayment);

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
            $this->handlePaidInvoice($result['invoice'], 'alipay_query:'.($result['payment_no'] ?? $payment->payment_no));
        }
    }

    /**
     * 后台发起订单退款
     */
    public function refundOrder(Order $order, array $payload = [], array $context = []): array
    {
        $refundMethod = trim((string) ($payload['refund_method'] ?? 'original'));
        $paymentGateway = $this->detectPrimaryPaymentGateway($order);
        $traceId = trim((string) ($context['trace_id'] ?? ''));

        $this->referralService->assertOrderRewardRefundable($order);

        $result = match ($refundMethod) {
            'balance' => $this->refundOrderToBalance($order, $payload, $context, 'balance', '退回余额'),
            'original' => match ($paymentGateway) {
                'alipay' => $this->refundOrderByAlipay($order, $payload, $context),
                'balance' => $this->refundOrderToBalance($order, $payload, $context, 'original', '原路退款'),
                default => throw new BusinessException('当前支付方式不支持原路退款'),
            },
            default => throw new BusinessException('不支持的退款方式'),
        };

        if (($result['already_refunded'] ?? false) !== true) {
            $this->referralService->reverseRewardForRefundedOrder($order, $traceId !== '' ? "refund:{$traceId}" : "refund:order:{$order->id}");
        }

        return $result;
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
                $this->syncProjection($payment);

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

                $balanceAfter = $this->setUserBalance($lockedUser, $this->getUserBalance($lockedUser) + $refundAmount);
                $this->createBalanceLog(
                    (int) $lockedUser->id,
                    FinanceLedgerEventType::INVOICE_REFUND,
                    $refundAmount,
                    $balanceAfter,
                    (int) $lockedInvoice->id,
                    '账单退款 '.(string) $lockedInvoice->invoice_no
                );

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
                $this->syncProjection($payment);

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
        $startedAt = microtime(true);
        $invoice = $invoice->fresh(['order']) ?? $invoice;

        if ((int) $invoice->status !== InvoiceStatus::PAID) {
            return;
        }

        $orderId = (int) ($invoice->order?->id ?? 0);
        $invoiceType = (string) ($invoice->type ?? $invoice->order?->type ?? '');

        $latency = [
            'coupon_sync_schedule_ms' => 0,
            'admin_notify_schedule_ms' => 0,
            'business_flow_dispatch_ms' => 0,
        ];

        // 开通 / 履约：优先走 Order 路径（包含完整上游 Mofang 链路），降级走 Invoice-only
        $stepStartedAt = microtime(true);
        if (in_array($invoiceType, ['renew', 'upgrade'], true)) {
            if ($orderId > 0) {
                $this->processPaidOrderFulfillmentById($orderId);
            } else {
                $this->provisionPaidInvoice($invoice);
            }
        } else {
            if ($orderId > 0) {
                $this->paidOrderBusinessFlowDispatcher->dispatchPaidInvoice($invoice, $traceId);
            } else {
                $this->provisionPaidInvoice($invoice);
                $this->dispatchInvoiceOnlyReferralReward($invoice, $traceId);
            }
        }
        $latency['business_flow_dispatch_ms'] = $this->elapsedMilliseconds($stepStartedAt);

        // 优惠券同步（仅当存在 order 时，CouponService 仍以 order 为归属单位）
        $stepStartedAt = microtime(true);
        try {
            if ($invoice->order) {
                $this->couponService->syncOrderCouponUsageAfterResponse($invoice->order);
            }
        } catch (\Throwable $exception) {
            Log::warning('[购买链路] 支付成功后优惠券同步调度失败', [
                'invoice_id' => (int) $invoice->id,
                'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                'trace_id' => (string) ($traceId ?? ''),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
        $latency['coupon_sync_schedule_ms'] = $this->elapsedMilliseconds($stepStartedAt);

        // 管理员通知：统一走 invoice 入口
        $stepStartedAt = microtime(true);
        try {
            $this->adminOrderNotificationService->notifyInvoicePaidAfterResponse($invoice);
        } catch (\Throwable $exception) {
            Log::warning('[购买链路] 支付成功后管理员通知调度失败', [
                'invoice_id' => (int) $invoice->id,
                'invoice_no' => (string) ($invoice->invoice_no ?? ''),
                'trace_id' => (string) ($traceId ?? ''),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
        $latency['admin_notify_schedule_ms'] = $this->elapsedMilliseconds($stepStartedAt);

        Log::info('[购买链路] 支付成功后处理耗时', array_merge($latency, [
            'invoice_id' => (int) $invoice->id,
            'invoice_no' => (string) ($invoice->invoice_no ?? ''),
            'invoice_type' => $invoiceType,
            'order_id' => $orderId,
            'trace_id' => (string) ($traceId ?? ''),
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]));
    }

    /**
     * 直接根据账单执行开通履约（无订单场景）
     */
    private function provisionPaidInvoice(Invoice $invoice): void
    {
        if ((int) $invoice->status !== InvoiceStatus::PAID) {
            return;
        }

        try {
            $invoiceType = (string) ($invoice->type ?? '');
            if ($invoiceType === 'renew') {
                $this->serviceRenewService->processPaidRenewInvoice($invoice);

                return;
            }

            if ($invoiceType === 'upgrade') {
                app(ServiceTrafficPackageService::class)
                    ->processPaidTrafficPackageInvoice($invoice);

                return;
            }

            $this->provisionService->processPaidInvoice($invoice);
        } catch (\Throwable $exception) {
            Log::error('[支付后自动开通] 基于账单的开通失败', [
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no ?? '',
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * 对无订单的账单触发推荐奖励（after-response / 同步兜底）
     */
    private function dispatchInvoiceOnlyReferralReward(Invoice $invoice, ?string $traceId): void
    {
        $callback = function () use ($invoice, $traceId): void {
            try {
                $this->referralService->rewardForPaidInvoice($invoice, $traceId);
            } catch (\Throwable $exception) {
                Log::error('[支付后推荐奖励] 基于账单的奖励处理失败', [
                    'invoice_id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no ?? '',
                    'trace_id' => $traceId,
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        };

        if (app()->runningInConsole()) {
            $callback();

            return;
        }

        app()->terminating($callback);
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

            if ($order->type === 'upgrade') {
                app(ServiceTrafficPackageService::class)
                    ->processPaidTrafficPackageOrder($order);

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
        $relations = ['invoice', 'product.supplier', 'service'];

        static $hasUserReferrals = null;
        if ($hasUserReferrals === null) {
            $hasUserReferrals = Schema::hasTable('user_referrals');
        }
        if ($hasUserReferrals) {
            $relations[] = 'user.referralProfile';
        } else {
            $relations[] = 'user';
        }

        $order = Order::query()
            ->with($relations)
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
            $this->syncProjection($pendingPayment);
        }
    }

    private function closeOtherPendingPaymentsForOrder(Order $order, int $excludePaymentId, string $reason): void
    {
        $invoiceId = (int) ($order->invoice?->id ?? 0);

        $pendingPayments = Payment::query()
            ->where('status', PaymentStatus::PENDING)
            ->where('id', '!=', $excludePaymentId)
            ->where(function ($query) use ($order, $invoiceId) {
                $query->where('order_id', $order->id);

                if ($invoiceId > 0) {
                    $query->orWhere('invoice_id', $invoiceId);
                }
            })
            ->lockForUpdate()
            ->get();

        foreach ($pendingPayments as $pendingPayment) {
            $callbackRaw = (array) ($pendingPayment->callback_raw ?? []);
            $callbackRaw['closed_reason'] = $reason;

            $pendingPayment->forceFill([
                'status' => PaymentStatus::FAILED,
                'callback_raw' => $callbackRaw,
            ])->save();
            $this->syncProjection($pendingPayment);
        }
    }

    private function resolveOrderPayableAmount(Order $order): float
    {
        return round(
            max(
                (float) ($order->amount ?? 0)
                - (float) ($order->discount ?? 0)
                - (float) ($order->paid_amount ?? 0),
                0
            ),
            2
        );
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
        return 'RFD'.$payment->payment_no;
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

    private function getUserBalance(User $user): float
    {
        return round((float) $user->getRawOriginal('balance', $user->balance), 2);
    }

    private function setUserBalance(User $user, float $balance): string
    {
        $normalized = number_format(round($balance, 2), 2, '.', '');
        $user->forceFill(['balance' => $normalized])->save();

        if (User::accountTableAvailable()) {
            UserAccount::query()->updateOrCreate(
                ['user_id' => (int) $user->id],
                ['cash_balance' => $normalized]
            );
        }

        return $normalized;
    }

    private function createBalanceLog(
        int $userId,
        string $eventType,
        float $changeAmount,
        string $balanceAfter,
        ?int $referenceId = null,
        string $remark = '',
        array $context = [],
    ): void {
        $log = BalanceLog::query()->create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'change_amount' => number_format($changeAmount, 2, '.', ''),
            'balance_after' => $balanceAfter,
            'reference_id' => $referenceId && $referenceId > 0 ? $referenceId : null,
            'remark' => $remark,
        ]);

        if (Schema::hasTable('account_transactions')) {
            DB::table('account_transactions')
                ->where('origin_type', 'balance_log')
                ->where('origin_id', (int) $log->id)
                ->update([
                    'operator' => trim((string) ($context['operator'] ?? '')) ?: null,
                    'trace_id' => trim((string) ($context['trace_id'] ?? '')) ?: null,
                ]);
        }
    }

    private function withLock(string $lockKey, int $seconds, callable $callback, string $timeoutMessage): mixed
    {
        try {
            return Cache::lock($lockKey, $seconds)->block(5, $callback);
        } catch (LockTimeoutException) {
            throw new BusinessException($timeoutMessage);
        }
    }

    public function syncProjection(Payment $payment): Payment
    {
        $payment->syncPaymentCallbackProjection();

        if (! Schema::hasTable('payment_callbacks')) {
            return $payment->fresh() ?? $payment;
        }

        return $payment->fresh(['callbacks']) ?? $payment;
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
