<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Finance\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {}

    /**
     * 支付宝异步通知
     */
    public function alipayNotify(Request $request)
    {
        Log::info('[支付宝回调] 收到通知', [
            'payment_no' => (string) $request->input('out_trade_no', ''),
            'trade_no' => (string) $request->input('trade_no', ''),
            'trade_status' => (string) $request->input('trade_status', ''),
            'app_id' => (string) $request->input('app_id', ''),
        ]);

        try {
            $success = $this->paymentService->handleAlipayNotify($request->all());
        } catch (\Throwable $exception) {
            Log::warning('[支付宝回调] 处理失败，已按 fail 响应', [
                'payment_no' => (string) $request->input('out_trade_no', ''),
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
            $success = false;
        }

        // 支付宝要求返回纯文本 success / fail
        return response($success ? 'success' : 'fail', 200)
            ->header('Content-Type', 'text/plain');
    }
}
