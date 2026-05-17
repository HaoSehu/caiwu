<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\AdminOrderNotificationService;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\CouponService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\PaymentGateway\AlipayFaceToFaceService;
use App\Services\Provisioning\ProvisionService;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\Referral\ReferralService;
use Tests\TestCase;

class OrderPaymentOrderBindingRegressionTest extends TestCase
{
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
            $this->createMock(AlipayFaceToFaceService::class),
            $this->createMock(ServiceRenewService::class),
            $this->createMock(ReferralService::class),
            $this->createMock(PaidOrderBusinessFlowDispatcher::class),
            $this->createMock(AdminOrderNotificationService::class),
            $this->createMock(CouponService::class),
            new InvoiceService,
        );

        $payment = $service->payOrderByBalance($order, $user, ['trace_id' => 'order-balance-regression']);

        $this->assertDatabaseHas('payments', [
            'id' => (int) $payment->id,
            'order_id' => (int) $order->id,
            'invoice_id' => (int) $invoice->id,
            'status' => PaymentStatus::SUCCESS,
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

        $latestPayment = Payment::query()->findOrFail((int) $payment->id);
        $this->assertSame((int) $order->id, (int) $latestPayment->order_id);
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
            $alipayService,
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
        $this->assertNotSame('', (string) $result['balance_payment_no']);
        $this->assertNotSame('', (string) $result['payment_no']);

        $this->assertDatabaseHas('payments', [
            'payment_no' => $result['balance_payment_no'],
            'order_id' => (int) $order->id,
            'invoice_id' => (int) $invoice->id,
            'gateway' => 'balance',
            'status' => PaymentStatus::SUCCESS,
            'amount' => '20.00',
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
}
