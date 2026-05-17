<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\PaymentService;
use Illuminate\Http\Request;

class RechargeController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private CheckoutSecurityService $checkoutSecurityService,
    ) {}

    /**
     * 创建充值订单（支付宝预下单）
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:50000'],
        ]);

        $result = $this->paymentService->rechargeByAlipay(
            $request->user(),
            (float) $data['amount']
        );

        $payment = Payment::query()
            ->where('payment_no', (string) ($result['payment_no'] ?? ''))
            ->where('user_id', $request->user()->id)
            ->where('gateway', 'alipay')
            ->whereNull('invoice_id')
            ->first();

        if (! $payment) {
            return $this->error(40400, '充值支付记录不存在');
        }

        return $this->success(
            array_merge($result, $this->checkoutSecurityService->issueRechargePollToken($payment, (int) $request->user()->id)),
            '充值二维码已生成'
        );
    }

    /**
     * 轮询充值状态
     */
    public function status(Request $request, string $paymentNo)
    {
        $data = $request->validate([
            'poll_token' => ['required', 'string', 'min:20', 'max:120'],
        ]);

        $payment = Payment::where('payment_no', $paymentNo)
            ->where('user_id', $request->user()->id)
            ->where('gateway', 'alipay')
            ->whereNull('invoice_id')
            ->firstOrFail();

        $this->checkoutSecurityService->assertRechargePollToken(
            (string) $data['poll_token'],
            $payment,
            (int) $request->user()->id
        );

        $result = $this->paymentService->queryRechargeStatus($payment);

        if ($result['paid']) {
            $request->user()->refresh();
            $result['balance'] = number_format((float) $request->user()->balance, 2, '.', '');
        }

        return $this->success($result);
    }
}
