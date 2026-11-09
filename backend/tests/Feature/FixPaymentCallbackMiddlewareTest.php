<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentGatewayCode;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Models\IntegrationPlugin;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginDomain;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use ReflectionProperty;
use Tests\TestCase;

/**
 * M-37 回归：回调验签中间件未包裹 verifyNotify——验签实现一旦抛异常
 * （插件进程里的公钥解析、配置损坏等），异常逃逸成 500，网关按失败重试，
 * 且 500 响应体会把内部异常暴露给外部。
 *
 * 期望：任何 Throwable 都记 warning（参数经脱敏）并按网关约定回失败 ack：
 * 通用网关回 200 fail 文本，支付宝回 200 fail 文本（支付宝据此重投）。
 */
class FixPaymentCallbackMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    private function registerThrowingGateway(string $key, bool $throwOnBuildResponse = false): void
    {
        $registry = app(PaymentGatewayRegistry::class);
        $gateways = new ReflectionProperty(PaymentGatewayRegistry::class, 'gateways');
        $gateways->setAccessible(true);

        // 真实插件网关可能已注册同 key：先禁用插件并清空注册表再注册替身
        // （DatabaseTransactions 会回滚插件状态）。
        if (Schema::hasTable('integration_plugins')) {
            IntegrationPlugin::query()
                ->where('domain', PluginDomain::PAYMENT)
                ->update(['status' => IntegrationPlugin::STATUS_DISABLED]);
        }
        $gateways->setValue($registry, []);

        $registry->register(new class($key, $throwOnBuildResponse) implements PaymentGatewayInterface
        {
            public function __construct(
                private readonly string $key,
                private readonly bool $throwOnBuildResponse,
            ) {}

            public function key(): string
            {
                return $this->key;
            }

            public function name(): string
            {
                return '回调验签异常测试替身';
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function matchesMerchantId(?string $merchantId): bool
            {
                return true;
            }

            public function supportsAction(string $action): bool
            {
                return false;
            }

            public function precreate(PaymentPrecreateRequest $request): PaymentPrecreateResult
            {
                return new PaymentPrecreateResult(qrCode: '', outTradeNo: $request->outTradeNo);
            }

            public function query(string $outTradeNo): PaymentQueryResult
            {
                return new PaymentQueryResult(tradeStatus: '', tradeNo: '', outTradeNo: $outTradeNo, totalAmount: '0.00');
            }

            public function verifyNotify(array $payload): bool
            {
                throw new \RuntimeException('verifyNotify 内部异常（公钥解析失败模拟）');
            }

            public function buildNotifyResponse(bool $success): Response
            {
                if ($this->throwOnBuildResponse) {
                    throw new \RuntimeException('buildNotifyResponse 异常模拟');
                }

                return response($success ? 'success' : 'fail', 200)->header('Content-Type', 'text/plain');
            }
        });
    }

    public function test_generic_callback_verify_exception_returns_fail_ack(): void
    {
        $this->registerThrowingGateway(PaymentGatewayCode::ALIPAY);

        $response = $this->post('/api/v2/client/payment/notify/alipay', [
            'out_trade_no' => 'PAY-MW-1',
            'key' => 'should-not-leak',
            'sign' => 'fake-sign',
        ]);

        // 绝不能 500：按网关约定返回 200 fail，让网关自然重投。
        $response->assertOk();
        $this->assertSame('fail', $response->getContent());
    }

    public function test_generic_callback_build_response_exception_still_returns_fail_ack(): void
    {
        $this->registerThrowingGateway(PaymentGatewayCode::ALIPAY, throwOnBuildResponse: true);

        $response = $this->post('/api/v2/client/payment/notify/alipay', ['out_trade_no' => 'PAY-MW-2']);

        $response->assertOk();
        $this->assertSame('fail', $response->getContent());
    }

    public function test_alipay_callback_verify_exception_returns_fail_ack(): void
    {
        $this->registerThrowingGateway(PaymentGatewayCode::ALIPAY);

        $response = $this->post('/api/v2/client/payment/alipay/notify', [
            'out_trade_no' => 'PAY-MW-3',
            'sign' => 'fake-sign',
            'sign_type' => 'RSA2',
        ]);

        $response->assertOk();
        $this->assertSame('fail', $response->getContent());
    }
}
