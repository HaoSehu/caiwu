<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\BalanceLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\AdminOrderNotificationService;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CouponService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Order\OrderService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\PaymentGateway\AlipayFaceToFaceService;
use App\Services\Provisioning\ProvisionService;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\Referral\ReferralService;
use Tests\TestCase;

class OrderPaymentOrderBindingRegressionTest extends TestCase
{
    /**
     * @return array{0: User, 1: Order, 2: Invoice}
     */
    private function createUserOrderInvoice(string $prefix, string $amount = '50.00', string $balance = '30.00'): array
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => strtolower($prefix).'-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => $prefix,
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
        $user->forceFill(['balance' => $balance])->save();

        $order = Order::query()->create([
            'order_no' => strtoupper($prefix).'ORD'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'amount' => $amount,
            'discount' => '0.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PENDING,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => strtoupper($prefix).'INV'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'normal',
            'amount' => $amount,
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDay(),
        ]);

        return [$user, $order, $invoice];
    }

    private function makePaymentService(AlipayFaceToFaceService $alipayService): PaymentService
    {
        return new PaymentService(
            $this->createMock(ProvisionService::class),
            $this->makePaymentGatewayManagerForTest($alipayService),
            $this->createMock(ServiceRenewService::class),
            $this->createMock(ReferralService::class),
            $this->createMock(PaidOrderBusinessFlowDispatcher::class),
            $this->createMock(AdminOrderNotificationService::class),
            $this->createMock(CouponService::class),
            new InvoiceService,
        );
    }

    public function test_payment_session_token_can_be_issued_without_invoice(): void
    {
        $order = Order::query()->create([
            'order_no' => 'ORDSESSION'.strtoupper(bin2hex(random_bytes(4))),
            'user_id' => 1,
            'type' => 'new',
            'amount' => '20.00',
            'discount' => '2.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PENDING,
        ]);

        $payload = app(CheckoutSecurityService::class)->issuePaymentSession($order, 1);

        $this->assertIsString($payload['session_token'] ?? null);
        $this->assertNotSame('', (string) ($payload['session_token'] ?? ''));
    }

    public function test_pay_order_by_balance_persists_payment_order_id(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'order-balance-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Order Balance',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
        $user->forceFill(['balance' => '100.00'])->save();

        $order = Order::query()->create([
            'order_no' => 'ORDBAL'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'amount' => '50.00',
            'discount' => '5.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PENDING,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INV'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'normal',
            'amount' => '45.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDay(),
        ]);

        $service = new PaymentService(
            $this->createMock(ProvisionService::class),
            $this->makePaymentGatewayManagerForTest($this->createMock(AlipayFaceToFaceService::class)),
            $this->createMock(ServiceRenewService::class),
            $this->createMock(ReferralService::class),
            $this->createMock(PaidOrderBusinessFlowDispatcher::class),
            $this->createMock(AdminOrderNotificationService::class),
            $this->createMock(CouponService::class),
            new InvoiceService,
        );

        $invoice = $service->payOrderByBalance($order, $user, ['trace_id' => 'order-balance-regression']);

        $this->assertDatabaseMissing('payments', [
            'order_id' => (int) $order->id,
            'gateway' => 'balance',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => (int) $order->id,
            'status' => OrderStatus::PAID,
            'paid_amount' => '45.00',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => (int) $invoice->id,
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '45.00',
        ]);

        $this->assertSame('55.00', User::query()->findOrFail((int) $user->id)->balance);
    }

    public function test_pay_by_balance_and_alipay_records_balance_payment_no(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'mix-pay-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Mix Pay',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
        $user->forceFill(['balance' => '30.00'])->save();

        $order = Order::query()->create([
            'order_no' => 'ORDMIX'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'amount' => '50.00',
            'discount' => '0.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PENDING,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INVMIX'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'normal',
            'amount' => '50.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDay(),
        ]);

        $alipayService = $this->createMock(AlipayFaceToFaceService::class);
        $alipayService->method('isEnabled')->willReturn(true);
        $alipayService->method('precreate')->willReturn([
            'qr_code' => 'https://qr.alipay.test/mix-pay',
            'out_trade_no' => 'mock-out-trade-no',
        ]);

        $service = new PaymentService(
            $this->createMock(ProvisionService::class),
            $this->makePaymentGatewayManagerForTest($alipayService),
            $this->createMock(ServiceRenewService::class),
            $this->createMock(ReferralService::class),
            $this->createMock(PaidOrderBusinessFlowDispatcher::class),
            $this->createMock(AdminOrderNotificationService::class),
            $this->createMock(CouponService::class),
            new InvoiceService,
        );

        $result = $service->payByBalanceAndAlipay($invoice, $user, 20.00, ['trace_id' => 'mix-pay-regression']);

        $this->assertSame('20.00', $result['balance_amount']);
        $this->assertSame('30.00', $result['amount']);
        $this->assertArrayNotHasKey('balance_payment_no', $result);
        $this->assertNotSame('', (string) $result['payment_no']);

        $this->assertDatabaseMissing('payments', [
            'invoice_id' => (int) $invoice->id,
            'gateway' => 'balance',
        ]);
        $this->assertDatabaseHas('payments', [
            'payment_no' => $result['payment_no'],
            'order_id' => (int) $order->id,
            'invoice_id' => (int) $invoice->id,
            'gateway' => 'alipay',
            'status' => PaymentStatus::PENDING,
            'amount' => '30.00',
        ]);
        $this->assertSame('10.00', User::query()->findOrFail((int) $user->id)->balance);
    }

    public function test_mix_pay_precreate_failure_rolls_back_balance_deduction(): void
    {
        [$user, $order, $invoice] = $this->createUserOrderInvoice('mixfail');

        $alipayService = $this->createMock(AlipayFaceToFaceService::class);
        $alipayService->method('isEnabled')->willReturn(true);
        $alipayService->method('precreate')->willThrowException(new BusinessException('网关失败'));

        try {
            $this->makePaymentService($alipayService)->payByBalanceAndAlipay($invoice, $user, 20.00, ['trace_id' => 'mix-fail']);
            $this->fail('Expected precreate failure.');
        } catch (BusinessException $exception) {
            $this->assertSame('网关失败', $exception->getMessage());
        }

        $this->assertSame('30.00', User::query()->findOrFail((int) $user->id)->balance);
        $this->assertDatabaseHas('invoices', [
            'id' => (int) $invoice->id,
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => (int) $order->id,
            'paid_amount' => '0.00',
            'status' => OrderStatus::PENDING,
        ]);
        $this->assertDatabaseHas('balance_logs', [
            'user_id' => (int) $user->id,
            'reference_id' => (int) $invoice->id,
            'event_type' => \App\Constants\FinanceLedgerEventType::INVOICE_PAYMENT,
            'change_amount' => '-20.00',
        ]);
        $this->assertDatabaseHas('balance_logs', [
            'user_id' => (int) $user->id,
            'reference_id' => (int) $invoice->id,
            'event_type' => \App\Constants\FinanceLedgerEventType::INVOICE_REFUND,
            'change_amount' => '20.00',
        ]);
        $this->assertSame(2, BalanceLog::query()
            ->where('user_id', (int) $user->id)
            ->where('reference_id', (int) $invoice->id)
            ->count());
    }

    public function test_cancel_mix_paid_invoice_refunds_reserved_balance(): void
    {
        [$user, $order, $invoice] = $this->createUserOrderInvoice('mixcancel');

        $alipayService = $this->createMock(AlipayFaceToFaceService::class);
        $alipayService->method('isEnabled')->willReturn(true);
        $alipayService->method('precreate')->willReturn([
            'qr_code' => 'https://qr.alipay.test/mix-cancel',
            'out_trade_no' => 'mock-out-trade-no',
        ]);

        $this->makePaymentService($alipayService)->payByBalanceAndAlipay($invoice, $user, 20.00, ['trace_id' => 'mix-cancel']);

        app(\App\Services\Finance\CheckoutService::class)->cancel($invoice->fresh(), [
            'actor_type' => 'client',
            'actor_user_id' => (int) $user->id,
            'trace_id' => 'mix-cancel',
        ]);

        $this->assertSame('30.00', User::query()->findOrFail((int) $user->id)->balance);
        $this->assertDatabaseHas('invoices', [
            'id' => (int) $invoice->id,
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::CANCELLED,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => (int) $order->id,
            'paid_amount' => '0.00',
        ]);
    }

    public function test_mix_pay_success_sets_order_paid_amount_to_full_invoice_amount(): void
    {
        [$user, $order, $invoice] = $this->createUserOrderInvoice('mixsuccess');

        $alipayService = $this->createMock(AlipayFaceToFaceService::class);
        $alipayService->method('isEnabled')->willReturn(true);
        $alipayService->method('precreate')->willReturn([
            'qr_code' => 'https://qr.alipay.test/mix-success',
            'out_trade_no' => 'mock-out-trade-no',
        ]);

        $result = $this->makePaymentService($alipayService)->payByBalanceAndAlipay($invoice, $user, 20.00, ['trace_id' => 'mix-success']);
        $payment = Payment::query()->where('payment_no', (string) $result['payment_no'])->firstOrFail();

        $tradeNo = 'TRADE-MIX-SUCCESS-'.strtoupper(bin2hex(random_bytes(4)));
        $queryResult = [
            'trade_status' => 'TRADE_SUCCESS',
            'trade_no' => $tradeNo,
            'out_trade_no' => (string) $payment->payment_no,
            'total_amount' => '30.00',
            'raw' => [
                'trade_status' => 'TRADE_SUCCESS',
                'trade_no' => $tradeNo,
                'out_trade_no' => (string) $payment->payment_no,
                'total_amount' => '30.00',
            ],
        ];
        $alipayService->method('query')->willReturn($queryResult);

        $this->makePaymentService($alipayService)->queryAlipayStatus($payment);

        $this->assertDatabaseHas('orders', [
            'id' => (int) $order->id,
            'status' => OrderStatus::PAID,
            'paid_amount' => '50.00',
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => (int) $invoice->id,
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '50.00',
        ]);
    }

    public function test_mix_pay_refund_to_balance_refunds_full_invoice_amount(): void
    {
        [$user, $order, $invoice] = $this->createUserOrderInvoice('mixrefund');
        $tradeNo = 'TRADE-MIX-REFUND-'.strtoupper(bin2hex(random_bytes(4)));

        Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'invoice_id' => (int) $invoice->id,
            'gateway' => 'alipay',
            'trade_no' => $tradeNo,
            'amount' => '30.00',
            'status' => PaymentStatus::SUCCESS,
            'callback_raw' => [
                'mix_payment' => true,
                'balance_amount' => '20.00',
            ],
            'paid_at' => now(),
        ]);

        $invoice->forceFill([
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '50.00',
            'paid_at' => now(),
        ])->save();
        $order->forceFill([
            'status' => OrderStatus::PAID,
            'paid_amount' => '50.00',
            'paid_at' => now(),
        ])->save();
        $user->forceFill(['balance' => '10.00'])->save();

        $alipayService = $this->createMock(AlipayFaceToFaceService::class);
        $result = $this->makePaymentService($alipayService)->refundInvoiceToBalance($user, $invoice, [
            'remark' => '组合支付退款',
        ]);

        $this->assertSame('50.00', (string) ($result['refund']['refund_amount'] ?? ''));
        $this->assertSame('60.00', User::query()->findOrFail((int) $user->id)->balance);
        $this->assertDatabaseHas('orders', [
            'id' => (int) $order->id,
            'status' => OrderStatus::REFUNDED,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => (int) $invoice->id,
            'status' => InvoiceStatus::REFUNDED,
        ]);
    }

    public function test_admin_manual_invoice_entry_does_not_create_payment_for_manual_gateway(): void
    {
        [$user, $order, $invoice] = $this->createUserOrderInvoice('manualpay');

        app(OrderService::class)->updateManualPaymentStatus($order, [
            'action' => 'mark_paid',
            'amount' => '50.00',
            'payment_gateway' => 'manual',
            'trade_no' => 'MANUAL-ENTRY-'.strtoupper(bin2hex(random_bytes(4))),
            'remark' => '后台确认入账',
        ], [
            'operator_id' => 1,
            'operator_name' => 'tester',
            'trace_id' => 'manual-entry-regression',
        ]);

        $this->assertDatabaseMissing('payments', [
            'invoice_id' => (int) $invoice->id,
            'gateway' => 'manual',
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => (int) $invoice->id,
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '50.00',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => (int) $order->id,
            'status' => OrderStatus::PAID,
            'paid_amount' => '50.00',
        ]);
    }

    public function test_admin_manual_invoice_entry_does_not_create_payment_for_offline_gateway(): void
    {
        [$user, $order, $invoice] = $this->createUserOrderInvoice('offlinepay');

        app(OrderService::class)->updateManualPaymentStatus($order, [
            'action' => 'mark_paid',
            'amount' => '50.00',
            'payment_gateway' => 'offline',
            'trade_no' => 'OFFLINE-ENTRY-'.strtoupper(bin2hex(random_bytes(4))),
            'remark' => '线下收款确认',
        ], [
            'operator_id' => 1,
            'operator_name' => 'tester',
            'trace_id' => 'offline-entry-regression',
        ]);

        $this->assertDatabaseMissing('payments', [
            'invoice_id' => (int) $invoice->id,
            'gateway' => 'offline',
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => (int) $invoice->id,
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '50.00',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => (int) $order->id,
            'status' => OrderStatus::PAID,
            'paid_amount' => '50.00',
        ]);
    }

    public function test_admin_manual_invoice_entry_does_not_create_real_gateway_payment(): void
    {
        [$user, $order, $invoice] = $this->createUserOrderInvoice('manualalipay');

        app(OrderService::class)->updateManualPaymentStatus($order, [
            'action' => 'mark_paid',
            'amount' => '50.00',
            'payment_gateway' => 'alipay',
            'trade_no' => 'ALI-MANUAL-ENTRY-'.strtoupper(bin2hex(random_bytes(4))),
            'remark' => '后台确认支付宝入账',
        ], [
            'operator_id' => 1,
            'operator_name' => 'tester',
            'trace_id' => 'manual-alipay-entry-regression',
        ]);

        $this->assertDatabaseMissing('payments', [
            'invoice_id' => (int) $invoice->id,
            'gateway' => 'alipay',
            'status' => PaymentStatus::SUCCESS,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => (int) $invoice->id,
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '50.00',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => (int) $order->id,
            'status' => OrderStatus::PAID,
            'paid_amount' => '50.00',
        ]);
    }
}
