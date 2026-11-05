<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentGatewayCode;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\User\AccountPolicyService;
use App\Support\Money;
use App\Support\VersionedJson;

class ClientInvoicePaymentWorkflowService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly BalanceInvoicePaymentService $balancePayments,
        private readonly GatewayInvoicePaymentService $gatewayPayments,
        private readonly MixPaymentService $mixPayments,
        private readonly PaymentGatewayManager $paymentGateways,
        private readonly CheckoutSecurityService $checkoutSecurity,
        private readonly CheckoutService $checkout,
        private readonly AccountPolicyService $accountPolicy,
    ) {}

    /**
     * @param  array<string, mixed>  $expiredContext
     * @return array<string, int|string>
     */
    public function summary(User $user, array $expiredContext): array
    {
        $this->checkout->cancelExpiredUnpaidInvoicesForUser((int) $user->id, $expiredContext);

        // 未付口径统一走 InvoiceUnpaidAggregate；计数列沿用历史别名 unpaid 以保持响应契约
        $row = InvoiceUnpaidAggregate::applyUnpaidSelects(
            Invoice::query()->where('user_id', $user->id),
            'unpaid'
        )
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS paid', [InvoiceStatus::PAID])
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'unpaid' => (int) ($row?->unpaid ?? 0),
            'paid' => (int) ($row?->paid ?? 0),
            'unpaid_amount' => number_format((float) ($row?->unpaid_amount ?? 0), 2, '.', ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function create(User $user, array $payload, string $idempotencyKey, array $context): array
    {
        $invoice = $this->checkout->create((int) $user->id, $payload, array_merge($context, [
            'idempotency_key' => $idempotencyKey,
        ]));
        $invoice->loadMissing($this->invoiceRelations());

        return $this->invoicePayload($invoice);
    }

    /**
     * @param  array<string, mixed>  $operationContext
     * @param  array<string, mixed>  $expiredContext
     * @return array<string, mixed>
     */
    public function payByBalance(
        User $user,
        int $invoiceId,
        string $paymentSessionToken,
        array $operationContext,
        array $expiredContext,
    ): array {
        $this->accountPolicy->assertSatisfied($user, 'payment');
        $invoice = $this->cancelExpiredInvoice($user, $invoiceId, $expiredContext);
        $this->checkoutSecurity->assertInvoicePaymentSessionToken($paymentSessionToken, $invoice, (int) $user->id);

        $paidInvoice = $this->balancePayments->payByBalance($invoice, $user, $operationContext);
        $invoice->refresh()->load($this->invoiceRelations());
        $user->refresh();

        return [
            'gateway' => 'balance',
            'amount' => number_format((float) $paidInvoice->paid_amount, 2, '.', ''),
            'paid_at' => $paidInvoice->paid_at?->format('Y-m-d H:i:s'),
            'cash_balance' => number_format((float) $user->balance, 2, '.', ''),
            'invoice' => $this->invoicePayload($invoice),
        ];
    }

    /**
     * @param  array<string, mixed>  $operationContext
     * @param  array<string, mixed>  $expiredContext
     * @return array<string, mixed>
     */
    public function payByBalanceAndAlipay(
        User $user,
        int $invoiceId,
        string $paymentSessionToken,
        float $balanceAmount,
        array $operationContext,
        array $expiredContext,
        string $ipAddress,
    ): array {
        $this->accountPolicy->assertSatisfied($user, 'payment');
        $invoice = $this->cancelExpiredInvoice($user, $invoiceId, $expiredContext);
        $this->checkoutSecurity->assertInvoicePaymentSessionToken($paymentSessionToken, $invoice, (int) $user->id);

        $result = $this->mixPayments->payByBalanceAndGateway($invoice, $user, $balanceAmount, PaymentGatewayCode::ALIPAY, $operationContext);
        $invoice->refresh()->load($this->invoiceRelations());
        $payment = $this->findInvoicePayment(
            $user,
            $invoice,
            (string) ($result['payment_no'] ?? ''),
            PaymentGatewayCode::ALIPAY,
        );

        return [
            ...$result,
            ...$this->checkoutSecurity->issueInvoicePaymentPollToken($payment, $invoice, (int) $user->id, $ipAddress),
            'invoice' => $this->invoicePayload($invoice),
        ];
    }

    /**
     * @param  array<string, mixed>  $operationContext
     * @param  array<string, mixed>  $expiredContext
     * @return array<string, mixed>
     */
    public function payByGateway(
        User $user,
        int $invoiceId,
        string $paymentSessionToken,
        string $gateway,
        string $paymentType,
        array $operationContext,
        array $expiredContext,
        string $ipAddress,
    ): array {
        $this->accountPolicy->assertSatisfied($user, 'payment');
        $invoice = $this->cancelExpiredInvoice($user, $invoiceId, $expiredContext);
        $this->checkoutSecurity->assertInvoicePaymentSessionToken($paymentSessionToken, $invoice, (int) $user->id);

        $gateway = PaymentGatewayCode::normalize($gateway) ?: PaymentGatewayCode::ALIPAY;
        $selectedOption = $this->gatewayOption($gateway, $paymentType);
        if ($selectedOption === null) {
            throw new BusinessException('当前没有可用支付方式，请联系管理员开启支付渠道');
        }

        $gatewayContext = $paymentType !== '' ? ['payment_type' => $paymentType] : [];
        $result = $this->gatewayPayments->payByGateway(
            $invoice,
            $user,
            $gateway,
            array_merge($operationContext, $gatewayContext),
        );
        $payment = $this->findInvoicePayment($user, $invoice, (string) ($result['payment_no'] ?? ''), $gateway);

        // 用户端只读 channel_name；plugin_name 仅供管理端与日志，两维度不得拼接成一个展示串。
        $channelName = (string) ($selectedOption['channel_name'] ?? ($selectedOption['label'] ?? PaymentGatewayCode::channelLabel($gateway, $paymentType)));
        $payload = [
            'gateway' => $gateway,
            'gateway_key' => $gateway,
            'gateway_label' => $channelName,
            'channel_name' => $channelName,
            'plugin_name' => (string) ($selectedOption['plugin_name'] ?? ''),
        ];
        if ($paymentType !== '') {
            $payload['payment_type'] = $paymentType;
            $payload['payment_type_label'] = $channelName;
        }

        return array_merge(
            $payload,
            $result,
            $this->checkoutSecurity->issueInvoicePaymentPollToken($payment, $invoice, (int) $user->id, $ipAddress),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function queryGatewayStatus(
        User $user,
        int $invoiceId,
        string $paymentNo,
        string $gateway,
        string $pollToken,
        string $ipAddress,
    ): array {
        $invoice = $this->findInvoiceForUser($user, $invoiceId);
        $gateway = PaymentGatewayCode::normalize($gateway);
        $paymentQuery = Payment::query()
            ->where('payment_no', $paymentNo)
            ->where('invoice_id', $invoice->id)
            ->where('user_id', $user->id);

        if ($gateway !== '') {
            $paymentQuery->whereGatewayKey($gateway);
        } else {
            $paymentQuery->whereGatewayKeyIn(PaymentGatewayCode::thirdPartyGateways());
        }

        $payment = $paymentQuery->first();
        if (! $payment) {
            throw new BusinessException('未找到支付记录', 40400, 404);
        }

        $this->checkoutSecurity->assertInvoicePaymentPollToken(
            $pollToken,
            $payment,
            $invoice,
            (int) $user->id,
            $ipAddress,
        );

        $result = $this->gatewayPayments->queryGatewayPaymentStatus($payment);
        if (($result['paid'] ?? false) !== true) {
            return $result;
        }

        $invoice->refresh()->load($this->invoiceRelations());

        return [
            ...$result,
            'invoice' => $this->invoicePayload($invoice),
        ];
    }

    /**
     * @param  array<string, mixed>  $expiredContext
     */
    private function cancelExpiredInvoice(User $user, int $invoiceId, array $expiredContext): Invoice
    {
        return $this->checkout->cancelExpiredUnpaidInvoice(
            $this->findInvoiceForUser($user, $invoiceId),
            $expiredContext,
        );
    }

    private function findInvoiceForUser(User $user, int $invoiceId): Invoice
    {
        return Invoice::with($this->invoiceRelations())
            ->where('user_id', $user->id)
            ->findOrFail($invoiceId);
    }

    private function findInvoicePayment(User $user, Invoice $invoice, string $paymentNo, string $gateway): Payment
    {
        $payment = Payment::query()
            ->where('payment_no', $paymentNo)
            ->where('invoice_id', $invoice->id)
            ->where('user_id', $user->id)
            ->whereGatewayKey($gateway)
            ->first();

        if (! $payment) {
            throw new BusinessException('支付记录不存在', 40400, 404);
        }

        return $payment;
    }

    /**
     * @return list<string>
     */
    private function invoiceRelations(): array
    {
        return [
            'product:id,product_type,product_group_id,service_type_code,remark,config_options,purchase_requires',
            'order.product:id,product_type,product_group_id,service_type_code,remark,config_options,purchase_requires',
            'service',
            'payments',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Invoice $invoice): array
    {
        $detail = $this->invoices->clientDetail($invoice);
        $payableAmount = (string) ($detail['payable_amount'] ?? Money::format($invoice->payableAmount()));
        $paymentSecurity = $this->checkoutSecurity->issueInvoicePaymentSession($invoice, (int) $invoice->user_id);
        $sessionToken = (string) ($paymentSecurity['session_token'] ?? '');
        $canCancel = in_array((int) $invoice->status, [InvoiceStatus::UNPAID], true);

        $configSnapshot = VersionedJson::withoutMeta(is_array($invoice->config_snapshot) ? $invoice->config_snapshot : []) ?? [];
        $configPricingSnapshot = is_array($invoice->config_pricing_snapshot) && $invoice->config_pricing_snapshot !== []
            ? (VersionedJson::withoutMeta($invoice->config_pricing_snapshot) ?? [])
            : [];
        $couponSnapshot = VersionedJson::withoutMeta(is_array($invoice->coupon_snapshot) ? $invoice->coupon_snapshot : []) ?? [];

        return [
            ...$detail,
            'service_id' => (int) ($detail['service']['id'] ?? $invoice->service_id ?? 0),
            'pay_methods' => $this->availablePaymentMethods((float) $payableAmount),
            'can_cancel' => $canCancel,
            'payable_amount' => $payableAmount,
            'product' => array_merge(is_array($detail['product'] ?? null) ? $detail['product'] : [], [
                'config_options' => (array) ($invoice->product?->config_options ?? $invoice->order?->product?->config_options ?? []),
            ]),
            'config_snapshot' => $configSnapshot,
            'config_pricing_snapshot' => $configPricingSnapshot,
            'coupon' => (int) ($invoice->coupon_id ?? 0) > 0 ? [
                'id' => (int) $invoice->coupon_id,
                'name' => (string) ($couponSnapshot['name'] ?? ''),
                'description' => (string) ($couponSnapshot['description'] ?? ''),
                'discount_label' => (string) ($couponSnapshot['discount_label'] ?? ''),
                'discount_amount' => number_format((float) ($invoice->discount ?? 0), 2, '.', ''),
            ] : null,
            'payment_security' => [
                'can_pay' => $canCancel && $sessionToken !== '',
                'session_token' => $sessionToken,
                'expires_at' => $paymentSecurity['expires_at'] ?? null,
            ],
        ];
    }

    /**
     * 账单支付方式唯一组装点。
     *
     * 0 元账单与普通账单一样只展示「余额支付 + 第三方网关选项」，
     * 因为第三方入口对 0 元账单会明确拒绝。
     * 第三方部分与充值模块同源（PaymentGatewayManager::availableThirdPartyGatewayOptions()），
     * 详情接口（InvoiceController）与 create/pay 响应均复用本方法，新增/下架网关自动跟随。
     *
     * @return list<array<string, mixed>>
     */
    public function availablePaymentMethods(float $payableAmount): array
    {
        return [
            ['key' => 'balance', 'name' => '余额支付', 'label' => '账户余额', 'option_key' => 'balance', 'plugin_name' => '', 'channel_name' => '余额支付'],
            ...$this->paymentGateways->availableThirdPartyGatewayOptions(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function gatewayOption(string $gateway, string $paymentType): ?array
    {
        foreach ($this->paymentGateways->availableThirdPartyGatewayOptions() as $option) {
            if ((string) ($option['key'] ?? '') !== $gateway) {
                continue;
            }

            if ($paymentType !== '' && $paymentType !== trim((string) ($option['payment_type'] ?? ''))) {
                continue;
            }

            return $option;
        }

        return null;
    }
}
