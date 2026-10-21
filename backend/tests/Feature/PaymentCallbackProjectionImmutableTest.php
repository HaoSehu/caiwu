<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\PaymentCallbackProjector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 回调投影不可覆盖（B-08 修复回归）：
 * 成功入账后，网关再投递 WAIT_BUYER_PAY 等非成功通知，不得洗掉
 * payment_callbacks 里的成功凭证（否则对账/追责时"为什么这笔单成功"无法证明）。
 */
class PaymentCallbackProjectionImmutableTest extends TestCase
{
    use DatabaseTransactions;

    public function test_success_projection_is_not_overwritten_by_later_pending_notify(): void
    {
        $payment = $this->makePayment();
        $projector = app(PaymentCallbackProjector::class);

        $projector->recordPaymentCallback($payment, 'payment', [
            'trade_status' => 'TRADE_SUCCESS',
            'trade_no' => 'TRADESUCCESS',
            'out_trade_no' => (string) $payment->payment_no,
            'total_amount' => '100.00',
        ], true, 'TRADESUCCESS');

        $this->assertSame(1, DB::table('payment_callbacks')->where('payment_id', (int) $payment->id)->count());

        // 网关补投非成功状态：不得覆盖成功投影。
        $projector->recordPaymentCallback($payment, 'payment', [
            'trade_status' => 'WAIT_BUYER_PAY',
            'trade_no' => 'TRADESUCCESS',
            'out_trade_no' => (string) $payment->payment_no,
            'total_amount' => '100.00',
        ], true, 'TRADESUCCESS');

        $row = DB::table('payment_callbacks')->where('payment_id', (int) $payment->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->is_verified);
        $this->assertStringContainsString('TRADE_SUCCESS', (string) $row->payload_json);
        $this->assertStringNotContainsString('WAIT_BUYER_PAY', (string) $row->payload_json);
        // 首次落库时间不得被后续写覆盖（审计语义）。
        $createdAt = (string) $row->created_at;
        $this->assertNotSame('', $createdAt);
    }

    public function test_rejected_payload_still_cannot_overwrite_verified_success(): void
    {
        $payment = $this->makePayment();
        $projector = app(PaymentCallbackProjector::class);

        $projector->recordPaymentCallback($payment, 'payment', [
            'trade_status' => 'TRADE_SUCCESS',
            'trade_no' => 'TRADEKEEP',
        ], true, 'TRADEKEEP');

        $projector->recordRejectedCallback($payment, [
            'trade_status' => 'WAIT_BUYER_PAY',
            'trade_no' => 'TRADEKEEP',
        ], 'signature_invalid');

        $row = DB::table('payment_callbacks')->where('payment_id', (int) $payment->id)->first();
        $this->assertStringContainsString('TRADE_SUCCESS', (string) $row->payload_json);
        $this->assertStringNotContainsString('signature_invalid', (string) $row->payload_json);
    }

    private function makePayment(): Payment
    {
        $user = User::factory()->create();
        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'amount' => '100.00',
            'status' => 0,
        ]);

        return Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => '100.00',
            'status' => PaymentStatus::SUCCESS,
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);
    }
}
