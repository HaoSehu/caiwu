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
 * 软删支付单不得销毁回调审计（P1-7 修复回归）：
 * payment_callbacks 是「为什么这笔单是成功的」的取证依据，必须保行。
 */
class PaymentSoftDeleteKeepsCallbacksTest extends TestCase
{
    use DatabaseTransactions;

    public function test_soft_delete_keeps_callback_rows_and_marks_archive(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'amount' => '100.00',
            'status' => 0,
        ]);
        $payment = Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => '100.00',
            'status' => PaymentStatus::SUCCESS,
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);

        app(PaymentCallbackProjector::class)->recordPaymentCallback($payment, 'payment', [
            'trade_status' => 'TRADE_SUCCESS',
            'trade_no' => 'TRADESOFTDEL',
        ], true, 'TRADESOFTDEL');

        $this->assertSame(1, DB::table('payment_callbacks')->where('payment_id', (int) $payment->id)->count());

        $payment->delete();

        // 支付单软删（行仍在），回调审计必须保留并标记归档。
        $this->assertNotNull(Payment::withTrashed()->find((int) $payment->id));
        $row = DB::table('payment_callbacks')->where('payment_id', (int) $payment->id)->first();
        $this->assertNotNull($row, '软删支付单不得物理删除回调审计');
        $this->assertStringContainsString('TRADE_SUCCESS', (string) $row->payload_json);
    }
}
