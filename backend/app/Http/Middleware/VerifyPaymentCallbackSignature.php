<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Services\Integrations\Plugins\PaymentGatewayBindingResolver;
use App\Support\AuditParamRedactor;
use App\Support\PaymentChannelLabel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentCallbackSignature
{
    public function __construct(
        private readonly PaymentGatewayManager $paymentGatewayManager,
        private readonly PaymentGatewayBindingResolver $paymentGatewayBindingResolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $gatewayKey = $this->paymentGatewayBindingResolver->normalizeGatewayKey(
            trim((string) $request->route('gateway', ''))
        );
        $params = $request->all();

        try {
            $gateway = $this->paymentGatewayManager->gateway($gatewayKey);
        } catch (\Throwable $exception) {
            Log::warning('[支付回调] 网关不可用，路由层签名验证失败', array_merge(PaymentChannelLabel::logContextForGateway($gatewayKey), [
                'gateway' => $gatewayKey,
                'message' => $exception->getMessage(),
                'params' => $this->redactedParams($params),
            ]));

            return $this->failureResponse(null);
        }

        // 验签实现（含插件进程内的密钥解析）任何异常都不得逃逸成 500：
        // 500 会让网关持续重试并把异常暴露在响应体里；按网关约定回 fail 让回调自然重投。
        try {
            $verified = $gateway->verifyNotify($params);
        } catch (\Throwable $exception) {
            Log::warning('[支付回调] 路由层验签异常，已按 fail 响应', array_merge(PaymentChannelLabel::logContextForGateway($gatewayKey), [
                'gateway' => $gatewayKey,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
                'params' => $this->redactedParams($params),
            ]));

            return $this->failureResponse($gateway);
        }

        if (! $verified) {
            Log::warning('[支付回调] 路由层签名验证失败', array_merge(PaymentChannelLabel::logContextForGateway($gatewayKey), [
                'gateway' => $gatewayKey,
                'payment_no' => (string) $request->input('out_trade_no', ''),
                'trade_no' => (string) $request->input('trade_no', ''),
                'trade_status' => (string) $request->input('trade_status', ''),
                'params' => $this->redactedParams($params),
            ]));

            return $this->failureResponse($gateway);
        }

        return $next($request);
    }

    /**
     * 回调参数进入日志前的凭据剔除：回调报文本身可能携带 key/sign 一类字段。
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function redactedParams(array $params): array
    {
        return AuditParamRedactor::redact($params);
    }

    private function failureResponse(?PaymentGatewayInterface $gateway): Response
    {
        if ($gateway !== null) {
            try {
                return $gateway->buildNotifyResponse(false);
            } catch (\Throwable) {
                // 网关实现的 ack 构造同样不可信：回落到通用 fail 文本
            }
        }

        return response('fail', 200)->header('Content-Type', 'text/plain');
    }
}
