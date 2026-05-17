<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentStatus;
use App\Models\BalanceLog;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\AdminOrderNotificationService;
use App\Services\Finance\CouponService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\PaymentGateway\AlipayFaceToFaceService;
use App\Services\Provisioning\ProvisionService;
use App\Services\Provisioning\ServiceRenewService;
use App\Services\Referral\ReferralService;
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
            $alipayService,
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
        $this->assertDatabaseHas('users', [
            'id' => (int) $user->id,
            'balance' => '15.00',
        ]);
        $this->assertDatabaseHas('balance_logs', [
            'user_id' => (int) $user->id,
            'event_type' => 'recharge',
            'change_amount' => '5.00',
            'balance_after' => '15.00',
            'reference_id' => (int) $payment->id,
        ]);

        $resultAgain = $service->queryRechargeStatus($payment->fresh());

        $this->assertTrue($resultAgain['paid']);
        $this->assertSame(1, BalanceLog::query()
            ->where('user_id', (int) $user->id)
            ->where('event_type', 'recharge')
            ->where('reference_id', (int) $payment->id)
            ->count());
        $this->assertSame('15.00', User::query()->findOrFail($user->id)->balance);
    }
}
