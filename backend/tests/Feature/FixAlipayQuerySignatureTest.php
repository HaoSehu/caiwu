<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Services\Integrations\Payments\Concerns\WrapsPemKeys;
use Caiwu\Plugins\Gateways\AliPay\Lib\AlipayClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../../plugins/gateways/ali_pay/lib/AlipayClient.php';

/**
 * M-78 回归：支付宝主动查询响应未验签即入账。
 *
 * 同步响应的 sign 覆盖的是 *_response 节点原始 JSON 文本（与异步通知的
 * query-string 排序形态不同）。AlipayClient::query 在响应含 sign 且已配置公钥时
 * 必须验签通过，否则抛业务异常；无 sign 时返回结果但留痕。
 *
 * 固定测试密钥对（2048 位 RSA，仅用于测试）：与 AliPayClientVerifyNotifyTest 同源。
 */
class FixAlipayQuerySignatureTest extends TestCase
{
    use WrapsPemKeys;

    private const PRIVATE_KEY = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDOSXsieSbPXG1JKvgl4z/HpTm2kITCtBoMCKxXRdPJU5kNnOOd11+EVMrGUqPer7duLvK9LYIvV7lEM8yfdoDVQHOMEcdOOrGrYDXTyop5hm28deE1FHUaF6pmZW4V0z/CasvRjvSaeY03Jd5zujLGUaamawHMw7Zw7/9iWuKhsg+nvmAX3VWy09Eyo1iVMB/qTy0mWlRM3umMaQmEqQaBxPnv+msUBiB1ftC6O1KR5rsNAbHXuYDHwyjh0aKeZsXyyExxyo1Xkdh63y+mOJkjEHw6KfmQTTSEOLUTRExu5JgqUfE3iSXa3gYzwHa+dmARyta3LA/NyF/RX7G+1jlxAgMBAAECggEAAvvT7112ZtOU1BUDm6lZYTtIpf/vSlFTEB9UU3spKKAQK77RbwAau6it+dI1AxH3vVJUr1D4G/8GPmWLvTcNqX1MvoxSSEAilOfnwhK2T92BJajvKIv4HqK+06DTcmGIv6M4+U9lRn0MF8MGiut487PiInA+ntYqLnYriOISJZm4TNYIUbFG4CDa3vPdHXgCR8yw20QoUZqLA8uJlt/Sba5pRJx6bozce7hX90UVWfxMZRSzDTJLqaL2zhy3kH8ioJT6WLR+cbHbs78WJZrRo+zcIS0W2BGAt5Kda4i23z9NnX1PHsY3dPzX94c/cQM5o/vzv4XbAEQlCDjs7+t7kQKBgQD7M2qrYvw4hLhGxSc6ffLtLKqDh2HOBqY7vetidWY7pdzqbZkM7HVNMaShSdSafyvbR45VapHk1s9VtTEcbr1b1gj9zIuqxjQkKKHw6/6FLjvH9yRMH2BLiPzA2c13j8a9VFQM2NJbd/e1BHASxsfEUJfAsxlPR3vetti00A8QCQKBgQDSOmXkt7Bqjp3NgEIxT4lsrTjRsT3Rh5eY/NxMAPLn+vCallI7gsqWem4Ki188qCvFfraBEilKQ70KLf5v2RjCv28BIdWKogdJ4xL8a2v/GGl5IVE+2f/8NAQTJtxhEZJV3Ka+YXVwjoYvmKBzYYaB3KGXvoxdgzikM/4RBuFoKQKBgEAiFG3FS9eyKsoGs8k/NrJO4QnLmrDbCb3e8yWkC4HXCtvfN2ARTrFypzF2hA0dwritQC2Wk8D1BuI+7Fbx/u5fatRBULc8+zrwFMhJuCWQdDrNYjr9F7yxMLab5NVNo9Pan8rX4Hzx2BAjHentTjtUyCmukBupiHqH/DPNmsrhAoGBALlVIl7wUWafNB7m43P1WoGkdGLojkcZ8lu9D7kxXPOGFncx7mzocREX/crt85fV6kDB5MME78G41f8TbSvNiDan7YRh/4+oKCTLOwGs8hiBvPo8lRODvFjqwmzf5YjtpMRtMdLEuHPyMfnj5A/T+nChwRO1uKj39gdMjr8UAcOBAoGBAJn55iYPNSQPZIGgP0lKUrPVAxjzdLnKH6nQDuD0Nb6ClJZQ6+TJ6/C3AL7ZsLNWHKzFCgl2vS8Wh43lTcnPhU2AUdcxIQTvliNSbTDu4CjiUPYOiyqfnzCc/GdslkJVVYPQfrROJKro8XeC8sA6ja0nkQ0z958IDKs6jnYpShPJ';

    private const PUBLIC_KEY = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzkl7Inkmz1xtSSr4JeM/x6U5tpCEwrQaDAisV0XTyVOZDZzjnddfhFTKxlKj3q+3bi7yvS2CL1e5RDPMn3aA1UBzjBHHTjqxq2A108qKeYZtvHXhNRR1GheqZmVuFdM/wmrL0Y70mnmNNyXec7oyxlGmpmsBzMO2cO//YlriobIPp75gF91VstPRMqNYlTAf6k8tJlpUTN7pjGkJhKkGgcT57/prFAYgdX7QujtSkea7DQGx17mAx8Mo4dGinmbF8shMccqNV5HYet8vpjiZIxB8Oin5kE00hDi1E0RMbuSYKlHxN4kl2t4GM8B2vnZgEcrWtywPzchf0V+xvtY5cQIDAQAB';

    /**
     * 组装同步响应体：sign 覆盖节点原始 JSON 文本（不经 decode/encode 重建）。
     */
    private function responseBody(string $signedNodeJson, ?string $signOverride = null): string
    {
        $sign = $signOverride;
        if ($sign === null) {
            $privateKey = openssl_pkey_get_private($this->wrapPemKey(self::PRIVATE_KEY, 'PRIVATE KEY'));
            $this->assertNotFalse($privateKey, '测试固定私钥不可用');

            $signature = '';
            $this->assertTrue(openssl_sign($signedNodeJson, $signature, $privateKey, OPENSSL_ALGO_SHA256));
            $sign = base64_encode($signature);
        }

        return '{"alipay_trade_query_response":'.$signedNodeJson.',"sign":'.json_encode($sign, JSON_UNESCAPED_SLASHES).'}';
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function client(array $overrides = []): AlipayClient
    {
        return new AlipayClient(array_merge([
            'app_id' => '2021000000000000',
            'private_key' => self::PRIVATE_KEY,
            'alipay_public_key' => self::PUBLIC_KEY,
            'alipay_enabled' => '1',
        ], $overrides));
    }

    public function test_query_with_valid_signature_returns_result(): void
    {
        $nodeJson = '{"code":"10000","msg":"Success","buyer_logon_id":"abc***@example.com","out_trade_no":"PAY-ALI-Q1","trade_no":"2026110922001440000000000001","trade_status":"TRADE_SUCCESS","total_amount":"128.00"}';

        Http::fake(['*' => Http::response($this->responseBody($nodeJson))]);

        $result = $this->client()->query('PAY-ALI-Q1');

        $this->assertSame('TRADE_SUCCESS', $result['trade_status']);
        $this->assertSame('2026110922001440000000000001', $result['trade_no']);
        $this->assertSame('128.00', $result['total_amount']);
    }

    public function test_query_with_tampered_payload_throws(): void
    {
        // 签名覆盖原始节点文本；篡改 total_amount 后节点文本与签名不再匹配。
        $signedNodeJson = '{"code":"10000","msg":"Success","out_trade_no":"PAY-ALI-Q2","trade_no":"T2","trade_status":"TRADE_SUCCESS","total_amount":"0.01"}';
        $tamperedNodeJson = '{"code":"10000","msg":"Success","out_trade_no":"PAY-ALI-Q2","trade_no":"T2","trade_status":"TRADE_SUCCESS","total_amount":"999999.00"}';

        $privateKey = openssl_pkey_get_private($this->wrapPemKey(self::PRIVATE_KEY, 'PRIVATE KEY'));
        $this->assertNotFalse($privateKey, '测试固定私钥不可用');
        $signature = '';
        $this->assertTrue(openssl_sign($signedNodeJson, $signature, $privateKey, OPENSSL_ALGO_SHA256));

        $body = '{"alipay_trade_query_response":'.$tamperedNodeJson.',"sign":'.json_encode(base64_encode($signature), JSON_UNESCAPED_SLASHES).'}';
        Http::fake(['*' => Http::response($body)]);

        try {
            $this->client()->query('PAY-ALI-Q2');
            $this->fail('篡改后的查询响应未被验签拦截');
        } catch (BusinessException $exception) {
            $this->assertSame('支付宝查询响应验签失败', $exception->getMessage());
        }
    }

    public function test_query_with_invalid_signature_throws(): void
    {
        $nodeJson = '{"code":"10000","msg":"Success","out_trade_no":"PAY-ALI-Q3","trade_status":"TRADE_SUCCESS","total_amount":"1.00"}';

        Http::fake(['*' => Http::response($this->responseBody($nodeJson, base64_encode(str_repeat('bad!', 64))))]);

        try {
            $this->client()->query('PAY-ALI-Q3');
            $this->fail('非法签名未被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame('支付宝查询响应验签失败', $exception->getMessage());
        }
    }

    public function test_query_without_signature_returns_result(): void
    {
        $nodeJson = '{"code":"10000","msg":"Success","out_trade_no":"PAY-ALI-Q4","trade_status":"TRADE_SUCCESS","total_amount":"5.00"}';

        Http::fake(['*' => Http::response('{"alipay_trade_query_response":'.$nodeJson.'}')]);

        $result = $this->client()->query('PAY-ALI-Q4');

        $this->assertSame('TRADE_SUCCESS', $result['trade_status']);
        $this->assertSame('5.00', $result['total_amount']);
    }

    public function test_query_with_sign_but_no_configured_public_key_returns_result(): void
    {
        $nodeJson = '{"code":"10000","msg":"Success","out_trade_no":"PAY-ALI-Q5","trade_status":"WAIT_BUYER_PAY"}';

        Http::fake(['*' => Http::response($this->responseBody($nodeJson))]);

        // 未配置公钥无法验签：既有链路（测试/沙箱未配公钥）维持可用，仅留 warning。
        $result = $this->client(['alipay_public_key' => ''])->query('PAY-ALI-Q5');

        $this->assertSame('WAIT_BUYER_PAY', $result['trade_status']);
    }
}
