<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentStatus;
use App\Models\BalanceLog;
use App\Models\Invoice;
use App\Models\Order;
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

class OrderZeroAmountPaymentFlowTest extends TestCase
{
    public function test_payment_session_token_can_be_issued_for_zero_amount_order(): void
    {
        $order = Order::query()->create([
            'order_no' => 'ORDZEROSESS'.strtoupper(bin2hex(random_bytes(4))),
            'user_id' => 1,
            'type' => 'new',
            'amount' => '50.00',
            'discount' => '50.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PENDING,
        ]);

        $payload = app(CheckoutSecurityService::class)->issuePaymentSession($order, 1);

        $this->assertIsString($payload['session_token'] ?? null);
        $this->assertNotSame('', (string) ($payload['session_token'] ?? ''));
    }

    public function test_payment_session_token_is_empty_when_order_already_paid(): void
    {
        $order = Order::query()->create([
            'order_no' => 'ORDZEROPAID'.strtoupper(bin2hex(random_bytes(4))),
            'user_id' => 1,
            'type' => 'new',
            'amount' => '50.00',
            'discount' => '50.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);

        $payload = app(CheckoutSecurityService::class)->issuePaymentSession($order, 1);

        $this->assertSame('', (string) ($payload['session_token'] ?? ''));
    }

    public function test_pay_order_by_balance_records_zero_amount_order_as_free_payment(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'order-zero-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Order Zero',
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
            'order_no' => 'ORDZERO'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'amount' => '10.00',
            'discount' => '10.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PENDING,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INVZERO'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'normal',
            'amount' => '0.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDay(),
        ]);

        $balanceLogCountBefore = BalanceLog::query()->where('user_id', (int) $user->id)->count();

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

        $paidInvoice = $service->payOrderByBalance($order, $user, ['trace_id' => 'order-zero-regression']);

        $this->assertDatabaseMissing('payments', [
            'order_id' => (int) $order->id,
            'gateway' => 'free',
        ]);
        $this->assertDatabaseMissing('payments', [
            'order_id' => (int) $order->id,
            'gateway' => 'balance',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => (int) $order->id,
            'status' => OrderStatus::PAID,
            'paid_amount' => '0.00',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => (int) $invoice->id,
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '0.00',
        ]);

        $this->assertSame('100.00', User::query()->findOrFail((int) $user->id)->balance);
        $this->assertSame(
            $balanceLogCountBefore,
            BalanceLog::query()->where('user_id', (int) $user->id)->count(),
        );
    }
}
