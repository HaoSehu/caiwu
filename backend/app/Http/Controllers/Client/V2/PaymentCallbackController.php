<?php

namespace App\Http\Controllers\Client\V2;

use App\Constants\PaymentGatewayCode;
use App\Http\Controllers\Controller;
use App\Models\FinanceCutoverInbox;
use App\Services\Finance\GatewayInvoicePaymentService;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function __construct(
        private GatewayInvoicePaymentService $paymentService,
        private PaymentGatewayManager $paymentGatewayManager,
    ) {}

    /**
     * 第三方支付网关异步通知（通用入口）
     */
    public function notify(string $gateway, Request $request)
    {
        $gateway = trim($gateway);
        // 与验签中间件同口径归一化网关别名（ali_pay/alipay_f2f → alipay、yi_pay → yipay），
        // 避免处理失败后再用未注册的别名解析网关，异常逃出控制器变成 500。
        $gateway = PaymentGatewayCode::normalize($gateway);

        $captured = $this->captureDuringCutover($gateway, $request);
        if ($captured instanceof Response) {
            return $captured;
        }

        Log::info("[{$gateway}回调] 收到通知", [
            'gateway' => $gateway,
            'payload_keys' => array_keys($request->all()),
        ]);

        try {
            $success = $this->paymentService->handleGatewayNotify($gateway, $request->all());
        } catch (\Throwable $exception) {
            Log::warning("[{$gateway}回调] 处理失败，已按 fail 响应", [
                'gateway' => $gateway,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
            $success = false;
        }

        try {
            return $this->paymentGatewayManager->gateway($gateway)
                ->buildNotifyResponse($success);
        } catch (\Throwable $exception) {
            // 未注册网关等异常时按网关约定返回常量 fail 文本，保证该公开端点不产生 500
            Log::warning("[{$gateway}回调] 构建回调响应失败，已按 fail 响应", [
                'gateway' => $gateway,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return response('fail', 200)->header('Content-Type', 'text/plain');
        }
    }

    /**
     * 支付宝异步通知（历史回调地址，内部走通用网关入口）
     */
    public function alipayNotify(Request $request)
    {
        $captured = $this->captureDuringCutover(PaymentGatewayCode::ALIPAY, $request);
        if ($captured instanceof Response) {
            return $captured;
        }

        Log::info('[支付宝回调] 收到通知', [
            'payment_no' => (string) $request->input('out_trade_no', ''),
            'trade_no' => (string) $request->input('trade_no', ''),
            'trade_status' => (string) $request->input('trade_status', ''),
            'app_id' => (string) $request->input('app_id', ''),
        ]);

        try {
            $success = $this->paymentService->handleGatewayNotify(PaymentGatewayCode::ALIPAY, $request->all());
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

    /**
     * 切换窗口收件箱：`finance.cutover.inbox_enabled` 开启期间，支付回调只存
     * 原始报文并按网关约定 ack 成功（防止渠道重试风暴），窗口解除后由
     * `finance:replay-cutover-inbox` 统一重放入账。
     *
     * 设计取舍：不验签、不入账、不判断支付单是否存在——窗口内只做「诚实存储」，
     * 一切业务判定延迟到重放时进行（重放走正常 handleGatewayNotify 全链路）。
     */
    private function captureDuringCutover(string $gateway, Request $request): ?Response
    {
        if (! (bool) config('finance.cutover.inbox_enabled')) {
            return null;
        }

        $payload = $request->all();
        // 无原生通知 ID 的渠道用「商户单号:渠道单号:报文摘要」拼唯一键；
        // 同一回调在窗口内被渠道重试时 updateOrCreate 幂等覆盖，不产生重复行。
        // updateOrCreate 存在并发竞态（两请求同时 INSERT 撞唯一键），
        // 公开端点不允许 500：撞键时降级为 update 重试一次。
        $notificationId = (string) ($payload['out_trade_no'] ?? '')
            .':'.(string) ($payload['trade_no'] ?? '')
            .':'.substr(md5((string) $request->getContent()), 0, 16);

        $attributes = [
            'request_uri' => (string) $request->getRequestUri(),
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'headers' => collect($request->headers->all())
                ->only(['user-agent', 'content-type', 'x-request-id'])
                ->map(static fn ($values) => $values[0] ?? '')
                ->filter(static fn ($value) => (string) $value !== '')
                ->all(),
            'status' => FinanceCutoverInbox::STATUS_PENDING,
            'received_at' => now(),
            'replayed_at' => null,
            'error_msg' => null,
        ];
        $keys = ['channel' => $gateway, 'notification_id' => $notificationId];

        try {
            FinanceCutoverInbox::query()->updateOrCreate($keys, $attributes);
        } catch (QueryException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }

            FinanceCutoverInbox::query()->where($keys)->update($attributes);
        }

        Log::warning('[切换窗口] 支付回调已入收件箱，待窗口解除后重放', [
            'gateway' => $gateway,
            'notification_id' => $notificationId,
            'out_trade_no' => (string) ($payload['out_trade_no'] ?? ''),
        ]);

        try {
            return $this->paymentGatewayManager->gateway($gateway)->buildNotifyResponse(true);
        } catch (\Throwable $exception) {
            // 未注册网关等异常时按网关约定返回成功文本，保证公开端点不产生 500
            Log::warning('[切换窗口] 构建回调响应失败，已按成功文本应答', [
                'gateway' => $gateway,
                'message' => $exception->getMessage(),
            ]);

            return response('success', 200)->header('Content-Type', 'text/plain');
        }
    }
}
