<?php

namespace App\Http\Middleware;

use App\Constants\PaymentGatewayCode;
use App\Services\Integrations\Payments\PaymentGatewayManager;
use App\Support\AuditParamRedactor;
use App\Support\PaymentChannelLabel;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyAlipayCallbackSignature
{
    public function __construct(
        private readonly PaymentGatewayManager $paymentGatewayManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $params = $request->all();

        // 验签链路（插件进程内的公钥解析等）任何异常都不得逃逸成 500：
        // 支付宝收到非 success 响应会按退避策略重投，回 fail 文本让回调自然重试。
        try {
            $verified = $this->paymentGatewayManager->alipay()->verifyNotify($params);
        } catch (\Throwable $exception) {
            Log::warning('[支付宝回调] 路由层验签异常，已按 fail 响应', array_merge(PaymentChannelLabel::logContextForGateway(PaymentGatewayCode::ALIPAY), [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
                'params' => AuditParamRedactor::redact($params),
            ]));

            return response('fail', 200)->header('Content-Type', 'text/plain');
        }

        if (! $verified) {
            Log::warning('[支付宝回调] 路由层签名验证失败', array_merge(PaymentChannelLabel::logContextForGateway(PaymentGatewayCode::ALIPAY), [
                'payment_no' => (string) $request->input('out_trade_no', ''),
                'trade_no' => (string) $request->input('trade_no', ''),
                'trade_status' => (string) $request->input('trade_status', ''),
                'params' => AuditParamRedactor::redact($params),
            ]));

            return response('签名验证失败', 401)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return $next($request);
    }
}
