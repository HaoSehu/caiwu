<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Payments\Concerns\WrapsPemKeys;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use Caiwu\Plugins\Gateways\AliPay\Lib\AlipayClient;
use Caiwu\Plugins\Gateways\DemoPay\Lib\DemoPayService;
use Caiwu\Plugins\Gateways\YiPay\Lib\YiPayClient;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../../plugins/gateways/ali_pay/lib/AlipayClient.php';
require_once __DIR__.'/../../plugins/gateways/yi_pay/lib/YiPayClient.php';
require_once __DIR__.'/../../plugins/gateways/demo_pay/lib/DemoPayService.php';

/**
 * 网关客户端加固回归（M-35/M-36/M-40/M-41）。
 *
 * 固定测试密钥对（2048 位 RSA，仅用于测试，不可用于任何真实环境）：
 * 与 AliPayClientVerifyNotifyTest 同源，私钥造签名、公钥验签。
 */
class FixGatewayClientSecurityTest extends TestCase
{
    use WrapsPemKeys;

    private const PRIVATE_KEY = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDOSXsieSbPXG1JKvgl4z/HpTm2kITCtBoMCKxXRdPJU5kNnOOd11+EVMrGUqPer7duLvK9LYIvV7lEM8yfdoDVQHOMEcdOOrGrYDXTyop5hm28deE1FHUaF6pmZW4V0z/CasvRjvSaeY03Jd5zujLGUaamawHMw7Zw7/9iWuKhsg+nvmAX3VWy09Eyo1iVMB/qTy0mWlRM3umMaQmEqQaBxPnv+msUBiB1ftC6O1KR5rsNAbHXuYDHwyjh0aKeZsXyyExxyo1Xkdh63y+mOJkjEHw6KfmQTTSEOLUTRExu5JgqUfE3iSXa3gYzwHa+dmARyta3LA/NyF/RX7G+1jlxAgMBAAECggEAAvvT7112ZtOU1BUDm6lZYTtIpf/vSlFTEB9UU3spKKAQK77RbwAau6it+dI1AxH3vVJUr1D4G/8GPmWLvTcNqX1MvoxSSEAilOfnwhK2T92BJajvKIv4HqK+06DTcmGIv6M4+U9lRn0MF8MGiut487PiInA+ntYqLnYriOISJZm4TNYIUbFG4CDa3vPdHXgCR8yw20QoUZqLA8uJlt/Sba5pRJx6bozce7hX90UVWfxMZRSzDTJLqaL2zhy3kH8ioJT6WLR+cbHbs78WJZrRo+zcIS0W2BGAt5Kda4i23z9NnX1PHsY3dPzX94c/cQM5o/vzv4XbAEQlCDjs7+t7kQKBgQD7M2qrYvw4hLhGxSc6ffLtLKqDh2HOBqY7vetidWY7pdzqbZkM7HVNMaShSdSafyvbR45VapHk1s9VtTEcbr1b1gj9zIuqxjQkKKHw6/6FLjvH9yRMH2BLiPzA2c13j8a9VFQM2NJbd/e1BHASxsfEUJfAsxlPR3vetti00A8QCQKBgQDSOmXkt7Bqjp3NgEIxT4lsrTjRsT3Rh5eY/NxMAPLn+vCallI7gsqWem4Ki188qCvFfraBEilKQ70KLf5v2RjCv28BIdWKogdJ4xL8a2v/GGl5IVE+2f/8NAQTJtxhEZJV3Ka+YXVwjoYvmKBzYYaB3KGXvoxdgzikM/4RBuFoKQKBgEAiFG3FS9eyKsoGs8k/NrJO4QnLmrDbCb3e8yWkC4HXCtvfN2ARTrFypzF2hA0dwritQC2Wk8D1BuI+7Fbx/u5fatRBULc8+zrwFMhJuCWQdDrNYjr9F7yxMLab5NVNo9Pan8rX4Hzx2BAjHentTjtUyCmukBupiHqH/DPNmsrhAoGBALlVIl7wUWafNB7m43P1WoGkdGLojkcZ8lu9D7kxXPOGFncx7mzocREX/crt85fV6kDB5MME78G41f8TbSvNiDan7YRh/4+oKCTLOwGs8hiBvPo8lRODvFjqwmzf5YjtpMRtMdLEuHPyMfnj5A/T+nChwRO1uKj39gdMjr8UAcOBAoGBAJn55iYPNSQPZIGgP0lKUrPVAxjzdLnKH6nQDuD0Nb6ClJZQ6+TJ6/C3AL7ZsLNWHKzFCgl2vS8Wh43lTcnPhU2AUdcxIQTvliNSbTDu4CjiUPYOiyqfnzCc/GdslkJVVYPQfrROJKro8XeC8sA6ja0nkQ0z958IDKs6jnYpShPJ';

    private const PUBLIC_KEY = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzkl7Inkmz1xtSSr4JeM/x6U5tpCEwrQaDAisV0XTyVOZDZzjnddfhFTKxlKj3q+3bi7yvS2CL1e5RDPMn3aA1UBzjBHHTjqxq2A108qKeYZtvHXhNRR1GheqZmVuFdM/wmrL0Y70mnmNNyXec7oyxlGmpmsBzMO2cO//YlriobIPp75gF91VstPRMqNYlTAf6k8tJlpUTN7pjGkJhKkGgcT57/prFAYgdX7QujtSkea7DQGx17mAx8Mo4dGinmbF8shMccqNV5HYet8vpjiZIxB8Oin5kE00hDi1E0RMbuSYKlHxN4kl2t4GM8B2vnZgEcrWtywPzchf0V+xvtY5cQIDAQAB';

    private const APP_ID = '2021000000000000';

    private const API_ENDPOINT = 'https://pay.example.com/';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://api.example.test']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function alipay(array $overrides = []): AlipayClient
    {
        return new AlipayClient(array_merge([
            'app_id' => self::APP_ID,
            'private_key' => self::PRIVATE_KEY,
            'alipay_public_key' => self::PUBLIC_KEY,
            'alipay_enabled' => '1',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function yipay(array $overrides = []): YiPayClient
    {
        return new YiPayClient(array_merge([
            'merchant_id' => '10001',
            'merchant_key' => 'test-merchant-key',
            'sign_type' => 'MD5',
            'api_endpoint' => self::API_ENDPOINT,
            'payment_types' => ['alipay'],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function signNotifyParams(array $params): array
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params);

        $privateKey = openssl_pkey_get_private($this->wrapPemKey(self::PRIVATE_KEY, 'PRIVATE KEY'));
        $this->assertNotFalse($privateKey, '测试固定私钥不可用');

        $signature = '';
        $this->assertTrue(openssl_sign(urldecode(http_build_query($params)), $signature, $privateKey, OPENSSL_ALGO_SHA256));

        $params['sign'] = base64_encode($signature);
        $params['sign_type'] = 'RSA2';

        return $params;
    }

    private function withEnvironment(string $environment, callable $callback): mixed
    {
        $original = (string) $this->app['env'];
        $this->app['env'] = $environment;

        try {
            return $callback();
        } finally {
            $this->app['env'] = $original;
        }
    }

    // ---- M-35：完整 PEM 公钥不得二次包装 ----

    public function test_verify_notify_accepts_full_pem_public_key(): void
    {
        $params = $this->signNotifyParams([
            'app_id' => self::APP_ID,
            'out_trade_no' => 'INV202611090001',
            'trade_no' => '2026110922001440000000000001',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '128.00',
        ]);

        $fullPem = "-----BEGIN PUBLIC KEY-----\n".wordwrap(self::PUBLIC_KEY, 64, "\n", true)."\n-----END PUBLIC KEY-----";

        $this->assertTrue($this->alipay(['alipay_public_key' => $fullPem])->verifyNotify($params));
    }

    public function test_verify_notify_accepts_bare_base64_public_key(): void
    {
        $params = $this->signNotifyParams([
            'app_id' => self::APP_ID,
            'out_trade_no' => 'INV202611090002',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '1.00',
        ]);

        $this->assertTrue($this->alipay()->verifyNotify($params));
    }

    public function test_precreate_signs_with_full_pem_private_key(): void
    {
        Http::fake([
            '*' => Http::response([
                'alipay_trade_precreate_response' => [
                    'code' => '10000',
                    'msg' => 'Success',
                    'qr_code' => 'https://qr.alipay.test/precreate',
                    'out_trade_no' => 'PAY-PEM-KEY-1',
                ],
            ]),
        ]);

        $fullPemPrivateKey = "-----BEGIN PRIVATE KEY-----\n".wordwrap(self::PRIVATE_KEY, 64, "\n", true)."\n-----END PRIVATE KEY-----";

        $this->alipay(['private_key' => $fullPemPrivateKey])->precreate('PAY-PEM-KEY-1', 10.0, '账户充值');

        $sent = [];
        Http::assertSent(function (Request $request) use (&$sent): bool {
            $sent = $request->data();

            return true;
        });

        $sign = (string) ($sent['sign'] ?? '');
        $this->assertNotSame('', $sign, 'precreate 未携带签名');

        unset($sent['sign']);
        ksort($sent);
        $stringToSign = urldecode(http_build_query($sent));

        $publicKey = openssl_pkey_get_public($this->wrapPemKey(self::PUBLIC_KEY, 'PUBLIC KEY'));
        $this->assertNotFalse($publicKey);
        $this->assertSame(1, openssl_verify($stringToSign, (string) base64_decode($sign, true), $publicKey, OPENSSL_ALGO_SHA256));
    }

    // ---- M-36：网关 RequestException 不得逃逸成 500 ----

    public function test_alipay_request_exception_becomes_business_exception(): void
    {
        Http::fake(static function (): never {
            throw new RequestException(new Response(new PsrResponse(502, [], 'gateway exploded')));
        });

        try {
            $this->alipay()->query('PAY-REQ-EX-1');
            $this->fail('支付宝网关 502 未被转换为业务异常');
        } catch (BusinessException $exception) {
            $this->assertSame('网关响应异常，请稍后重试', $exception->getMessage());
            $this->assertSame(42200, $exception->getErrorCode());
            $this->assertSame(422, $exception->getCode());
        }
    }

    public function test_yipay_request_exception_becomes_business_exception(): void
    {
        Http::fake(static function (): never {
            throw new RequestException(new Response(new PsrResponse(503, [], 'gateway exploded')));
        });

        try {
            $this->yipay()->query('PAY-REQ-EX-2');
            $this->fail('易支付网关 503 未被转换为业务异常');
        } catch (BusinessException $exception) {
            $this->assertSame('网关响应异常，请稍后重试', $exception->getMessage());
            $this->assertSame(42200, $exception->getErrorCode());
            $this->assertSame(422, $exception->getCode());
        }
    }

    // ---- M-40：易支付接口地址只允许公网 https ----

    public function test_yipay_endpoint_rejects_http_scheme_in_production(): void
    {
        $this->withEnvironment('production', function (): void {
            try {
                $this->yipay(['api_endpoint' => 'http://pay.example.com/']);
                $this->fail('生产环境 http 接口地址未被拒绝');
            } catch (BusinessException $exception) {
                $this->assertSame('易支付接口地址不合法', $exception->getMessage());
            }
        });
    }

    public function test_yipay_endpoint_allows_http_in_testing_environment(): void
    {
        $client = $this->yipay(['api_endpoint' => 'http://pay.example.com/']);

        $this->assertTrue($client->isEnabled());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function privateHostProvider(): array
    {
        return [
            'loopback' => ['https://127.0.0.1/'],
            'class-a-private' => ['https://10.1.2.3/'],
            'class-c-private' => ['https://192.168.1.10/'],
            'class-b-private-low' => ['https://172.16.0.1/'],
            'class-b-private-high' => ['https://172.31.255.254/'],
            'link-local' => ['https://169.254.1.1/'],
            'ipv6-loopback' => ['https://[::1]/'],
            'localhost' => ['https://localhost/'],
        ];
    }

    #[DataProvider('privateHostProvider')]
    public function test_yipay_endpoint_rejects_private_or_loopback_hosts(string $endpoint): void
    {
        try {
            $this->yipay(['api_endpoint' => $endpoint]);
            $this->fail('私网/回环接口地址未被拒绝: '.$endpoint);
        } catch (BusinessException $exception) {
            $this->assertSame('易支付接口地址不合法', $exception->getMessage());
        }
    }

    public function test_yipay_endpoint_accepts_public_https_host(): void
    {
        $client = $this->yipay(['api_endpoint' => 'https://pay.example.com/']);

        $this->assertTrue($client->isEnabled());
    }

    // ---- M-41：demo_pay 生产环境禁用 ----

    public function test_demo_pay_is_disabled_in_production(): void
    {
        $service = new DemoPayService;

        $this->withEnvironment('production', function () use ($service): void {
            $this->assertFalse($service->isEnabled(['enabled' => true]));
        });
    }

    public function test_demo_pay_precreate_throws_in_production(): void
    {
        $service = new DemoPayService;

        $this->withEnvironment('production', function () use ($service): void {
            try {
                $service->precreate(new PaymentPrecreateRequest(
                    outTradeNo: 'PAY-DEMO-1',
                    amount: 10.0,
                    subject: '账户充值',
                ));
                $this->fail('生产环境 demo_pay precreate 未被拒绝');
            } catch (BusinessException $exception) {
                $this->assertSame('演示支付网关在生产环境不可用', $exception->getMessage());
            }
        });
    }

    public function test_demo_pay_works_in_testing_environment(): void
    {
        $service = new DemoPayService;

        $this->assertTrue($service->isEnabled(['enabled' => true]));

        $result = $service->precreate(new PaymentPrecreateRequest(
            outTradeNo: 'PAY-DEMO-2',
            amount: 10.0,
            subject: '账户充值',
        ));

        $this->assertStringContainsString('PAY-DEMO-2', $result->qrCode);
    }
}
