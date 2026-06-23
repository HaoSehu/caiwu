<?php

namespace App\Http\Controllers\Client;

use App\Constants\PaymentGatewayCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Recharge\StatusRequest;
use App\Http\Requests\Client\Recharge\StoreRequest;
use App\Models\Payment;
use App\Services\Finance\CheckoutSecurityService;
use App\Services\Finance\PaymentService;

class RechargeController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private CheckoutSecurityService $checkoutSecurityService,
    ) {}

    /**
     * 创建充值订单（支付宝预下单）
     */
    public function store(StoreRequest $request)
    {
        $data = $request->validated();

        $result = $this->paymentService->rechargeByAlipay(
            $request->user(),
            (float) $data['amount']
        );

        $payment = Payment::query()
            ->where('payment_no', (string) ($result['payment_no'] ?? ''))
            ->where('user_id', $request->user()->id)
            ->where('gateway', PaymentGatewayCode::ALIPAY)
            ->whereNull('invoice_id')
            ->first();

        if (! $payment) {
            return $this->error(40400, '充值支付记录不存在');
        }

        return $this->success(
            array_merge($result, $this->checkoutSecurityService->issueRechargePollToken($payment, (int) $request->user()->id, $request->ip())),
            '充值二维码已生成'
        );
    }

    /**
     * 轮询充值状态
     */
    public function status(StatusRequest $request, string $paymentNo)
    {
        $data = $request->validated();

        $payment = Payment::where('payment_no', $paymentNo)
            ->where('user_id', $request->user()->id)
            ->where('gateway', PaymentGatewayCode::ALIPAY)
            ->firstOrFail();

        $this->checkoutSecurityService->assertRechargePollToken(
            (string) $data['poll_token'],
            $payment,
            (int) $request->user()->id,
            $request->ip()
        );

        $result = $this->paymentService->queryRechargeStatus($payment);

        if ($result['paid']) {
            $request->user()->refresh();
            $result['balance'] = number_format((float) $request->user()->balance, 2, '.', '');
        }

        return $this->success($result);
    }
}
