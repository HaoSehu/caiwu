<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Payments\PaymentGatewayOperationService;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 消费-在线流（第三方网关支付）：下单+预下单二维码、异步回调入口
 * （验签/商户号/金额三道闸 + 入账分派）与主动轮询入口（消毒/金额校验/3 秒微缓存）。
 * 入账事务体统一收敛到 InvoiceSettlementService；充值类（无 invoice_id）分派到
 * RechargePaymentService 到账；支付成功后的履约/返利编排交给 InvoicePaidOrchestrator。
 */
class GatewayInvoicePaymentService
{
    use PaymentGatewayFlow;

    private ?PaymentGatewayOperationService $gatewayOperations = null;

    public function __construct(
        private PaymentGatewayManager $paymentGatewayManager,
        private CheckoutSecurityService $checkoutSecurityService,
        private InvoiceSettlementService $invoiceSettlementService,
        private RechargePaymentService $rechargePaymentService,
        private InvoicePaidOrchestrator $paidInvoiceOrchestrator,
        private PaymentCallbackProjector $callbackProjector,
        private ?PaymentGatewayBindingResolver $paymentGatewayBindingResolver = null,
    ) {}

    /**
     * 第三方网关支付（通用入口，支持 Invoice）
     *
     * @return array<string, mixed>
     */
    public function payByGateway(Invoice $invoice, User $user, string $gateway, array $context = []): array
    {
        $resolvedGateway = $this->resolveGateway($gateway);

        throw_if(! $resolvedGateway->isEnabled(), new BusinessException(
            PaymentGatewayCode::label($gateway).'支付未启用'
        ));

        $traceId = $this->resolveTraceId($context, "{$gateway}:invoice:{$invoice->id}");
        $lockKey = "lock:pay:{$gateway}:invoice:{$invoice->id}";

        $payload = $this->withLock($lockKey, 20, function () use ($invoice, $user, $traceId, $gateway) {
            return DB::transaction(function () use ($invoice, $user, $traceId, $gateway) {
                $lockedInvoice = Invoice::query()
                    ->lockForUpdate()
                    ->with('order')
                    ->findOrFail($invoice->id);

                throw_if(
                    ! in_array((int) $lockedInvoice->status, [InvoiceStatus::UNPAID], true),
                    new BusinessException('账单状态异常，无法支付')
                );

                $amount = round((float) $lockedInvoice->amount - (float) ($lockedInvoice->paid_amount ?? 0), 2);
                throw_if($amount <= 0, new BusinessException('无需支付'));

                $payment = Payment::query()
                    ->where('invoice_id', $lockedInvoice->id)
                    ->whereGatewayKey($gateway)
                    ->where('status', PaymentStatus::PENDING)
                    ->where('amount', number_format($amount, 2, '.', ''))
                    ->latest('id')
                    ->first();

                if (! $payment) {
                    $payment = Payment::query()->create($this->paymentCreatePayload([
                        'payment_no' => Payment::generatePaymentNo(),
                        'user_id' => $user->id,
                        'order_id' => (int) ($lockedInvoice->order?->id ?? 0) ?: null,
                        'invoice_id' => $lockedInvoice->id,
                        'gateway' => $gateway,
                        'amount' => $amount,
                        'status' => PaymentStatus::PENDING,
                        'trace_id' => $traceId,
                        'callback_raw' => [
                            'source' => "{$gateway}_precreate",
                            'trace_id' => $traceId,
                        ],
                    ]));
                    $this->callbackProjector->syncProjection($payment);
                } else {
                    $this->ensurePaymentGatewayAudit($payment, $gateway, $traceId);
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
        $result = $this->precreateGatewayPayment(
            $gateway,
            $payment->payment_no,
            (float) $payment->amount,
            $subject,
            $this->resolveInvoicePaymentTimeoutExpress($lockedInvoice),
            $context
        );

        return [
            'payment_no' => $payment->payment_no,
            'qr_code' => $result['qr_code'],
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
        ];
    }

    /**
     * 第三方网关异步通知处理（通用入口）
     *
     * @param  array<string, mixed>  $params
     */
    public function handleGatewayNotify(string $gateway, array $params): bool
    {
        $resolvedGateway = $this->resolveGateway($gateway);
        $gatewayLabel = PaymentGatewayCode::label($gateway);

        if (! $resolvedGateway->verifyNotify($params)) {
            Log::warning("[{$gatewayLabel}回调] 签名验证失败", [
                'gateway' => $gateway,
                'payment_no' => (string) ($params['out_trade_no'] ?? ''),
                'trade_no' => (string) ($params['trade_no'] ?? ''),
                'trade_status' => (string) ($params['trade_status'] ?? ''),
            ]);
            $this->recordNotifyRejection($gateway, $params, '签名验证失败');

            return false;
        }

        $paymentNo = $params['out_trade_no'] ?? '';
        $tradeStatus = $params['trade_status'] ?? '';
        $tradeNo = $params['trade_no'] ?? '';

        // 支付单必须属于本次回调的网关，否则任一网关都能确认其他网关的订单
        $payment = Payment::query()->whereGatewayKey($gateway)->where('payment_no', $paymentNo)->first();
        if (! $payment) {
            Log::warning("[{$gatewayLabel}回调] 支付记录不存在或不属于该网关", [
                'payment_no' => $paymentNo,
                'gateway' => $gateway,
            ]);

            return false;
        }

        // 商户号校验（支付宝: app_id, 微信: mch_id, 易支付: pid）
        // 缺字段不得跳过：网关未配置商户号时回调直接拒绝，配置了就必须匹配
        $merchantId = $params['app_id'] ?? $params['mch_id'] ?? $params['pid'] ?? '';
        if (! $resolvedGateway->matchesMerchantId($merchantId)) {
            Log::warning("[{$gatewayLabel}回调] 商户号不匹配", [
                'payment_no' => $paymentNo,
                'merchant_id' => $merchantId,
            ]);
            $this->recordNotifyRejection($gateway, $params, '商户号不匹配');

            return false;
        }

        $notifyAmount = round((float) ($params['total_amount'] ?? $params['amount'] ?? $params['money'] ?? 0), 2);
        $expectedAmount = round((float) $payment->amount, 2);
        if ($notifyAmount <= 0 || abs($notifyAmount - $expectedAmount) > 0.0001) {
            Log::warning("[{$gatewayLabel}回调] 金额校验失败", [
                'payment_no' => $paymentNo,
                'expected_amount' => $expectedAmount,
                'notify_amount' => $notifyAmount,
            ]);
            $this->recordNotifyRejection($gateway, $params, '金额校验失败');

            return false;
        }

        $params['trace_id'] = $this->resolveTraceId(
            ['trace_id' => $params['trace_id'] ?? $payment->trace_id],
            "{$gateway}:callback:{$payment->id}"
        );
        $this->callbackProjector->recordPaymentCallback($payment, 'payment', $params, true, $tradeNo);

        // 各网关成功状态不同，由 queryGatewayPayment 的结果判断
        if (! in_array($tradeStatus, ['TRADE_SUCCESS', 'TRADE_FINISHED', 'SUCCESS'], true)) {
            Log::info("[{$gatewayLabel}回调] 非成功状态，已记录回调并跳过业务入账", [
                'payment_no' => $paymentNo,
                'trade_status' => $tradeStatus,
            ]);

            return true;
        }

        // 幂等：已处理过
        if ((int) $payment->status === PaymentStatus::SUCCESS) {
            $this->rechargePaymentService->ensureRechargeInvoiceProjection($payment);

            return true;
        }

        // 充值类（无 invoice_id）走充值到账逻辑
        if (! $payment->invoice_id) {
            $this->rechargePaymentService->completeRechargePayment($payment, $tradeNo, $params);

            return true;
        }

        try {
            $result = $this->invoiceSettlementService->settleCapturedGatewayPayment(
                $payment,
                $tradeNo,
                $params,
                "lock:{$gateway}:payment:{$payment->id}",
                "{$gatewayLabel}回调",
                'invoice_paid_by_gateway',
                '账单状态异常，无法处理支付回调',
                '支付回调处理中，请稍后重试',
            );
        } catch (BusinessException $exception) {
            Log::warning("[{$gatewayLabel}回调] 处理失败", [
                'payment_no' => $paymentNo,
                'trade_no' => $tradeNo,
                'message' => $exception->getMessage(),
            ]);

            return false;
        } catch (\Throwable $exception) {
            Log::error("[{$gatewayLabel}回调] 处理异常", [
                'payment_no' => $paymentNo,
                'trade_no' => $tradeNo,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return false;
        }

        if (($result['dispatch'] ?? false) && ($result['invoice'] ?? null) instanceof Invoice) {
            $this->paidInvoiceOrchestrator->handlePaidInvoice($result['invoice'], "{$gateway}:".($result['payment_no'] ?? $paymentNo));
        }

        return true;
    }

    /**
     * 拒绝回调留痕：能按网关+支付单号定位到支付单时落一条 is_verified=0 审计行。
     * 拒绝载荷不可信，投影器只在无既有投影行时插入，不会覆盖真实回调。
     */
    private function recordNotifyRejection(string $gateway, array $params, string $reason): void
    {
        $paymentNo = (string) ($params['out_trade_no'] ?? '');
        if ($paymentNo === '') {
            return;
        }

        $payment = Payment::query()->whereGatewayKey($gateway)->where('payment_no', $paymentNo)->first();
        if (! $payment instanceof Payment) {
            return;
        }

        try {
            $this->callbackProjector->recordRejectedCallback($payment, $params, $reason);
        } catch (\Throwable $exception) {
            Log::warning("[{$gateway}回调] 拒绝回调留痕失败", [
                'payment_no' => $paymentNo,
                'reason' => $reason,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 轮询第三方网关支付状态（通用入口）
     *
     * 注意：主动查询的响应数据格式与异步通知不同，
     *       不能复用 handleGatewayNotify() 的签名验证逻辑，
     *       需要独立处理支付成功的入账流程。
     *
     * @return array<string, mixed>
     */
    public function queryGatewayPaymentStatus(Payment $payment): array
    {
        if ((int) $payment->status === PaymentStatus::SUCCESS) {
            return ['paid' => true, 'trade_no' => $payment->trade_no];
        }

        $gateway = $payment->gatewayKey();

        // 支付页前端按秒轮询：按网关+支付单号做 3 秒服务端微缓存，把轮询 QPS 收敛为
        // 对网关的低频主动查询；支付成功通常伴随回调入账，3 秒延迟对体验无感。
        $queryCacheKey = 'payment:gateway:query:'.$gateway.':'.hash('sha256', (string) $payment->payment_no);
        $result = Cache::get($queryCacheKey);
        if (! is_array($result)) {
            $result = $this->queryGatewayPayment($gateway, $payment->payment_no);
            Cache::put($queryCacheKey, $result, now()->addSeconds(3));
        }

        if (in_array($result['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED', 'SUCCESS'], true)) {
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
     *
     * @param  array<string, mixed>  $queryResult
     */
    private function completePaymentFromQuery(Payment $payment, array $queryResult): void
    {
        $tradeNo = $queryResult['trade_no'] ?? '';
        $tradeStatus = $queryResult['trade_status'] ?? '';
        $gateway = $payment->gatewayKey();
        $gatewayLabel = PaymentGatewayCode::label($gateway);

        // 消毒网关标签：防止特殊字符注入锁键和日志（gateway 值来自数据库，理论上可被直接修改）
        $safeGateway = preg_replace('/[^a-z0-9_-]/i', '', $gateway);
        if ($safeGateway === '' || $safeGateway !== $gateway) {
            Log::error('网关标签包含非法字符', [
                'payment_id' => $payment->id,
                'raw_gateway' => $gateway,
                'safe_gateway' => $safeGateway,
            ]);
            throw new \RuntimeException('网关标签非法');
        }

        // 金额校验（total_amount 缺失或为零均视为异常，拒绝入账）
        $queryAmount = round((float) ($queryResult['total_amount'] ?? 0), 2);
        $expectedAmount = round((float) $payment->amount, 2);
        if ($queryAmount <= 0 || abs($queryAmount - $expectedAmount) > 0.0001) {
            Log::warning("[{$gatewayLabel}主动查询] 金额校验失败", [
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

        $queryPayload = array_merge($queryResult['raw'] ?? [], [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => $tradeNo,
            'trade_status' => $tradeStatus,
            'source' => 'active_query',
            'trace_id' => $this->resolveTraceId(
                ['trace_id' => $queryResult['trace_id'] ?? $payment->trace_id],
                "{$gateway}:query:{$payment->id}"
            ),
        ]);
        $this->callbackProjector->recordPaymentCallback($payment, 'payment', $queryPayload, true, $tradeNo);

        // 充值类（无 invoice_id）走充值到账逻辑
        if (! $payment->invoice_id) {
            $this->rechargePaymentService->completeRechargePayment($payment, $tradeNo, $queryPayload);

            return;
        }

        try {
            $result = $this->invoiceSettlementService->settleCapturedGatewayPayment(
                $payment,
                $tradeNo,
                $queryPayload,
                "lock:{$safeGateway}:payment:{$payment->id}",
                "{$gatewayLabel}主动查询",
                'invoice_paid_by_gateway_query',
                '账单状态异常，无法处理支付',
                '支付处理中，请稍后重试',
            );
        } catch (\Throwable $exception) {
            Log::error("[{$gatewayLabel}主动查询] 入账处理异常", [
                'payment_no' => $payment->payment_no,
                'trade_no' => $tradeNo,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        if (($result['dispatch'] ?? false) && ($result['invoice'] ?? null) instanceof Invoice) {
            $this->paidInvoiceOrchestrator->handlePaidInvoice($result['invoice'], "{$gateway}_query:".($result['payment_no'] ?? $payment->payment_no));
        }
    }
}
