<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginScanner;
use Caiwu\Plugins\Gateways\AliPay\Lib\AlipayClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

class RechargeGatewayFailureTest extends TestCase
{
    public function test_alipay_precreate_connection_failure_returns_business_exception(): void
    {
        config([
            'alipay.gateway' => 'https://openapi.alipay.com/gateway.do',
            'alipay.notify_url' => 'https://example.com/api/client/payment/alipay/notify',
            'alipay.app_id' => 'test-app-id',
            'alipay.private_key' => str_repeat('A', 200),
        ]);

        Http::fake(function (): never {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        $service = $this->makeAlipayClient();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('支付网关暂时不可用，请稍后重试');

        $this->invokePrivateMethod($service, 'request', [[
            'app_id' => 'test-app-id',
            'method' => 'alipay.trade.precreate',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => '{}',
            'sign' => 'signature',
        ]]);
    }

    public function test_ssl_certificate_problem_retries_without_verification_in_non_production(): void
    {
        config([
            'alipay.gateway' => 'https://openapi.alipay.com/gateway.do',
            'alipay.notify_url' => 'https://example.com/api/client/payment/alipay/notify',
            'alipay.app_id' => 'test-app-id',
            'alipay.private_key' => str_repeat('A', 200),
            'alipay.ssl_verify' => true,
            'alipay.ca_bundle' => '',
        ]);

        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('cURL error 60: SSL certificate problem: unable to get local issuer certificate');
            }

            return Http::response([
                'alipay_trade_precreate_response' => [
                    'code' => '10000',
                    'out_trade_no' => 'PAY202605272200000001',
                    'qr_code' => 'https://qr.example.com/pay',
                ],
            ]);
        });

        $service = $this->makeAlipayClient();

        $result = $this->invokePrivateMethod($service, 'request', [[
            'app_id' => 'test-app-id',
            'method' => 'alipay.trade.precreate',
            'format' => 'JSON',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => '{}',
            'sign' => 'signature',
        ]]);

        $this->assertSame(2, $attempts);
        $this->assertSame('10000', $result['alipay_trade_precreate_response']['code'] ?? null);
    }

    public function test_alipay_client_prefers_plugin_runtime_network_config(): void
    {
        config([
            'alipay.gateway' => 'https://openapi.alipay.com/gateway.do',
            'alipay.notify_url' => '',
            'alipay.ssl_verify' => true,
            'alipay.ca_bundle' => '',
        ]);

        $client = new AlipayClient([
            'gateway' => 'https://plugin-gateway.example.test/gateway.do',
            'notify_url' => 'https://pay.example.test/api/client/payment/alipay/notify',
            'ssl_verify' => false,
            'ca_bundle' => 'C:\\php\\extras\\ssl\\cacert.pem',
        ]);

        $this->assertSame('https://plugin-gateway.example.test/gateway.do', $this->getPrivateProperty($client, 'gateway'));
        $this->assertSame('https://pay.example.test/api/client/payment/alipay/notify', $this->getPrivateProperty($client, 'notifyUrl'));
        $this->assertFalse($this->getPrivateProperty($client, 'sslVerify'));
        $this->assertSame('C:\\php\\extras\\ssl\\cacert.pem', $this->getPrivateProperty($client, 'caBundle'));
    }

    public function test_precreate_notify_url_accepts_public_https_address(): void
    {
        config([
            'alipay.notify_url' => 'https://pay.example.com/api/client/payment/alipay/notify',
            'app.url' => 'http://127.0.0.1:8000',
        ]);

        $service = $this->makeAlipayClient();

        $this->assertSame(
            'https://pay.example.com/api/client/payment/alipay/notify',
            $this->invokePrivateMethod($service, 'resolvePrecreateNotifyUrl')
        );
    }

    public function test_precreate_notify_url_accepts_public_http_address(): void
    {
        config([
            'alipay.notify_url' => 'http://47.109.144.223:6107/api/client/payment/alipay/notify',
            'app.url' => 'http://127.0.0.1:8000',
        ]);

        $service = $this->makeAlipayClient();

        $this->assertSame(
            'http://47.109.144.223:6107/api/client/payment/alipay/notify',
            $this->invokePrivateMethod($service, 'resolvePrecreateNotifyUrl')
        );
    }

    public function test_precreate_notify_url_falls_back_to_frontend_url(): void
    {
        config([
            'alipay.notify_url' => '',
            'app.frontend_url' => 'http://47.109.144.223:6107',
            'app.url' => 'http://127.0.0.1:8000',
        ]);

        $service = $this->makeAlipayClient();

        $this->assertSame(
            'http://47.109.144.223:6107/api/client/payment/alipay/notify',
            $this->invokePrivateMethod($service, 'resolvePrecreateNotifyUrl')
        );
    }

    public function test_precreate_notify_url_rejects_local_backend_address(): void
    {
        config([
            'alipay.notify_url' => '',
            'app.frontend_url' => '',
            'app.url' => 'http://127.0.0.1:8000',
        ]);

        $service = $this->makeAlipayClient();

        $this->assertNull($this->invokePrivateMethod($service, 'resolvePrecreateNotifyUrl'));
    }

    private function invokePrivateMethod(object $target, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }

    private function makeAlipayClient(): AlipayClient
    {
        $manifest = app(PluginScanner::class)->requireManifest('payment', 'ali_pay');
        app(PluginFileLoader::class)->ensureLoaded($manifest);

        return new AlipayClient;
    }

    private function getPrivateProperty(object $target, string $property): mixed
    {
        $reflection = new \ReflectionProperty($target, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($target);
    }
}
