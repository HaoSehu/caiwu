<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentStatus;
use App\Models\AccountTransaction;
use App\Models\Invoice;
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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RechargeStatusBalanceRegressionTest extends TestCase
{
    public function test_query_recharge_status_updates_users_balance_and_balance_log(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'recharge-balance-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Recharge Balance',
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
        $user->forceFill(['balance' => '10.00'])->save();

        $payment = Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'invoice_id' => null,
            'gateway' => 'alipay',
            'amount' => '5.00',
            'status' => PaymentStatus::PENDING,
        ]);

        $tradeNo = 'TRADE-'.strtoupper(bin2hex(random_bytes(4)));

        $alipayService = $this->createMock(AlipayFaceToFaceService::class);
        $alipayService->expects($this->once())
            ->method('query')
            ->with((string) $payment->payment_no)
            ->willReturn([
                'trade_status' => 'TRADE_SUCCESS',
                'trade_no' => $tradeNo,
                'out_trade_no' => (string) $payment->payment_no,
                'total_amount' => '5.00',
                'raw' => [
                    'trade_status' => 'TRADE_SUCCESS',
                    'trade_no' => $tradeNo,
                    'out_trade_no' => (string) $payment->payment_no,
                    'total_amount' => '5.00',
                ],
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

        $result = $service->queryRechargeStatus($payment);

        $this->assertTrue($result['paid']);
        $this->assertSame($tradeNo, $result['trade_no']);
        $this->assertDatabaseHas('payments', [
            'id' => (int) $payment->id,
            'status' => PaymentStatus::SUCCESS,
            'trade_no' => $tradeNo,
        ]);
        $payment->refresh();
        $this->assertNotNull($payment->invoice_id);
        $this->assertDatabaseHas('invoices', [
            'id' => (int) $payment->invoice_id,
            'user_id' => (int) $user->id,
            'type' => 'recharge',
            'amount' => '5.00',
            'paid_amount' => '5.00',
        ]);
        $this->assertDatabaseHas('user_accounts', [
            'user_id' => (int) $user->id,
            'cash_balance' => '15.00',
        ]);
        $this->assertDatabaseHas('account_transactions', [
            'user_id' => (int) $user->id,
            'event_type' => 'recharge',
            'change_amount' => '5.00',
            'balance_after' => '15.00',
            'source_id' => (int) $payment->id,
        ]);

        $resultAgain = $service->queryRechargeStatus($payment->fresh());

        $this->assertTrue($resultAgain['paid']);
        $this->assertSame(1, AccountTransaction::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', 'recharge')
            ->where('source_id', (int) $payment->id)
            ->count());
        $this->assertSame(1, Invoice::query()
            ->where('user_id', (int) $user->id)
            ->where('type', 'recharge')
            ->where('amount', '5.00')
            ->count());
        $this->assertSame('15.00', User::query()->findOrFail($user->id)->balance);
    }

    public function test_recharge_completion_rolls_back_when_recharge_invoice_creation_fails(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'recharge-rollback-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Recharge Rollback',
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
        $user->forceFill(['balance' => '10.00'])->save();

        $payment = Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'invoice_id' => null,
            'gateway' => 'alipay',
            'amount' => '5.00',
            'status' => PaymentStatus::PENDING,
        ]);

        $tradeNo = 'TRADE-ROLLBACK-'.strtoupper(bin2hex(random_bytes(4)));
        $alipayService = $this->createMock(AlipayFaceToFaceService::class);
        $alipayService->expects($this->once())
            ->method('query')
            ->with((string) $payment->payment_no)
            ->willReturn([
                'trade_status' => 'TRADE_SUCCESS',
                'trade_no' => $tradeNo,
                'out_trade_no' => (string) $payment->payment_no,
                'total_amount' => '5.00',
                'raw' => [
                    'trade_status' => 'TRADE_SUCCESS',
                    'trade_no' => $tradeNo,
                    'out_trade_no' => (string) $payment->payment_no,
                    'total_amount' => '5.00',
                ],
            ]);

        $invoiceService = new class extends InvoiceService
        {
            public function createForRecharge(User $user, float $amount, ?Payment $payment = null, ?string $remark = null, ?string $traceId = null): Invoice
            {
                throw new \RuntimeException('invoice create failed');
            }
        };

        $service = new PaymentService(
            $this->createMock(ProvisionService::class),
            $this->makePaymentGatewayManagerForTest($alipayService),
            $this->createMock(ServiceRenewService::class),
            $this->createMock(ReferralService::class),
            $this->createMock(PaidOrderBusinessFlowDispatcher::class),
            $this->createMock(AdminOrderNotificationService::class),
            $this->createMock(CouponService::class),
            $invoiceService,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invoice create failed');

        try {
            $service->queryRechargeStatus($payment);
        } finally {
            $this->assertDatabaseHas('payments', [
                'id' => (int) $payment->id,
                'status' => PaymentStatus::PENDING,
                'invoice_id' => null,
            ]);
            $this->assertSame('10.00', User::query()->findOrFail($user->id)->balance);
            $this->assertSame(0, AccountTransaction::query()
                ->where('user_id', (int) $user->id)
                ->where('event_type', 'recharge')
                ->where('source_id', (int) $payment->id)
                ->count());
        }
    }

    public function test_recharge_status_endpoint_finds_paid_recharge_after_invoice_projection_is_created(): void
    {
        $suffix = bin2hex(random_bytes(4));

        $user = User::query()->create([
            'email' => 'recharge-http-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Recharge Http',
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
        $user->forceFill(['balance' => '15.00'])->save();
        $tradeNo = 'TRADE-HTTP-RECHARGE-'.strtoupper(bin2hex(random_bytes(4)));

        $payment = Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => (int) $user->id,
            'invoice_id' => null,
            'gateway' => 'alipay',
            'trade_no' => $tradeNo,
            'amount' => '5.00',
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
        ]);

        $this->assertNull($payment->invoice_id);

        $security = app(CheckoutSecurityService::class)->issueRechargePollToken($payment, (int) $user->id);
        Sanctum::actingAs($user);

        $this->getJson('/api/client/recharge/'.$payment->payment_no.'/status?poll_token='.$security['poll_token'])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.paid', true)
            ->assertJsonPath('data.trade_no', $tradeNo);

        $payment->refresh();
        $this->assertNotNull($payment->invoice_id);
        $this->assertInstanceOf(Invoice::class, Invoice::query()->find((int) $payment->invoice_id));
    }
}
