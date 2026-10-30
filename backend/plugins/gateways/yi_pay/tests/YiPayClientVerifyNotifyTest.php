<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\Payments\Concerns\WrapsPemKeys;
use Caiwu\Plugins\Gateways\YiPay\Lib\YiPayClient;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/YiPayClient.php';

/**
 * 易支付异步通知验签回归（MD5 与 RSA 两种签名口径）。
 *
 * 固定测试密钥对（2048 位 RSA，仅用于测试，不可用于任何真实环境）：
 * 私钥扮演商户侧、公钥扮演平台侧，两者配对即可覆盖 RSA 验签链路。
 * 固定而非运行时生成——本机 PHP 未配置 openssl.cnf 时 openssl_pkey_new() 会失败。
 */
class YiPayClientVerifyNotifyTest extends TestCase
{
    use WrapsPemKeys;

    private const PRIVATE_KEY = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDOSXsieSbPXG1JKvgl4z/HpTm2kITCtBoMCKxXRdPJU5kNnOOd11+EVMrGUqPer7duLvK9LYIvV7lEM8yfdoDVQHOMEcdOOrGrYDXTyop5hm28deE1FHUaF6pmZW4V0z/CasvRjvSaeY03Jd5zujLGUaamawHMw7Zw7/9iWuKhsg+nvmAX3VWy09Eyo1iVMB/qTy0mWlRM3umMaQmEqQaBxPnv+msUBiB1ftC6O1KR5rsNAbHXuYDHwyjh0aKeZsXyyExxyo1Xkdh63y+mOJkjEHw6KfmQTTSEOLUTRExu5JgqUfE3iSXa3gYzwHa+dmARyta3LA/NyF/RX7G+1jlxAgMBAAECggEAAvvT7112ZtOU1BUDm6lZYTtIpf/vSlFTEB9UU3spKKAQK77RbwAau6it+dI1AxH3vVJUr1D4G/8GPmWLvTcNqX1MvoxSSEAilOfnwhK2T92BJajvKIv4HqK+06DTcmGIv6M4+U9lRn0MF8MGiut487PiInA+ntYqLnYriOISJZm4TNYIUbFG4CDa3vPdHXgCR8yw20QoUZqLA8uJlt/Sba5pRJx6bozce7hX90UVWfxMZRSzDTJLqaL2zhy3kH8ioJT6WLR+cbHbs78WJZrRo+zcIS0W2BGAt5Kda4i23z9NnX1PHsY3dPzX94c/cQM5o/vzv4XbAEQlCDjs7+t7kQKBgQD7M2qrYvw4hLhGxSc6ffLtLKqDh2HOBqY7vetidWY7pdzqbZkM7HVNMaShSdSafyvbR45VapHk1s9VtTEcbr1b1gj9zIuqxjQkKKHw6/6FLjvH9yRMH2BLiPzA2c13j8a9VFQM2NJbd/e1BHASxsfEUJfAsxlPR3vetti00A8QCQKBgQDSOmXkt7Bqjp3NgEIxT4lsrTjRsT3Rh5eY/NxMAPLn+vCallI7gsqWem4Ki188qCvFfraBEilKQ70KLf5v2RjCv28BIdWKogdJ4xL8a2v/GGl5IVE+2f/8NAQTJtxhEZJV3Ka+YXVwjoYvmKBzYYaB3KGXvoxdgzikM/4RBuFoKQKBgEAiFG3FS9eyKsoGs8k/NrJO4QnLmrDbCb3e8yWkC4HXCtvfN2ARTrFypzF2hA0dwritQC2Wk8D1BuI+7Fbx/u5fatRBULc8+zrwFMhJuCWQdDrNYjr9F7yxMLab5NVNo9Pan8rX4Hzx2BAjHentTjtUyCmukBupiHqH/DPNmsrhAoGBALlVIl7wUWafNB7m43P1WoGkdGLojkcZ8lu9D7kxXPOGFncx7mzocREX/crt85fV6kDB5MME78G41f8TbSvNiDan7YRh/4+oKCTLOwGs8hiBvPo8lRODvFjqwmzf5YjtpMRtMdLEuHPyMfnj5A/T+nChwRO1uKj39gdMjr8UAcOBAoGBAJn55iYPNSQPZIGgP0lKUrPVAxjzdLnKH6nQDuD0Nb6ClJZQ6+TJ6/C3AL7ZsLNWHKzFCgl2vS8Wh43lTcnPhU2AUdcxIQTvliNSbTDu4CjiUPYOiyqfnzCc/GdslkJVVYPQfrROJKro8XeC8sA6ja0nkQ0z958IDKs6jnYpShPJ';

    private const PUBLIC_KEY = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzkl7Inkmz1xtSSr4JeM/x6U5tpCEwrQaDAisV0XTyVOZDZzjnddfhFTKxlKj3q+3bi7yvS2CL1e5RDPMn3aA1UBzjBHHTjqxq2A108qKeYZtvHXhNRR1GheqZmVuFdM/wmrL0Y70mnmNNyXec7oyxlGmpmsBzMO2cO//YlriobIPp75gF91VstPRMqNYlTAf6k8tJlpUTN7pjGkJhKkGgcT57/prFAYgdX7QujtSkea7DQGx17mAx8Mo4dGinmbF8shMccqNV5HYet8vpjiZIxB8Oin5kE00hDi1E0RMbuSYKlHxN4kl2t4GM8B2vnZgEcrWtywPzchf0V+xvtY5cQIDAQAB';

    private const MERCHANT_ID = '10001';

    private const MERCHANT_KEY = 'test-merchant-key';

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function client(array $overrides = []): YiPayClient
    {
        return new YiPayClient(array_merge([
            'merchant_id' => self::MERCHANT_ID,
            'merchant_key' => self::MERCHANT_KEY,
            'merchant_private_key' => self::PRIVATE_KEY,
            'platform_public_key' => self::PUBLIC_KEY,
            'sign_type' => 'MD5',
        ], $overrides));
    }

    /**
     * 与客户端 canonicalString 同口径：去掉 sign/sign_type，按键名排序，
     * 跳过 null/空串/数组，拼成 key=value&key=value。
     *
     * @param  array<string, mixed>  $payload
     */
    private function canonicalString(array $payload): string
    {
        unset($payload['sign'], $payload['sign_type']);
        ksort($payload, SORT_STRING);

        $pairs = [];
        foreach ($payload as $key => $value) {
            if ($value === null || $value === '' || is_array($value) || is_object($value)) {
                continue;
            }

            $pairs[] = $key.'='.(string) $value;
        }

        return implode('&', $pairs);
    }

    /**
     * @return array<string, mixed>
     */
    private function notifyPayload(): array
    {
        return [
            'pid' => self::MERCHANT_ID,
            'trade_no' => 'YIPAY20261026000001',
            'out_trade_no' => 'INV202610260002',
            'type' => 'alipay',
            'money' => '99.00',
            'trade_status' => 'TRADE_SUCCESS',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signMd5(array $payload): string
    {
        return md5($this->canonicalString($payload).self::MERCHANT_KEY);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function signRsa(array $payload): string
    {
        $privateKey = openssl_pkey_get_private($this->wrapPemKey(self::PRIVATE_KEY, 'PRIVATE KEY'));
        $this->assertNotFalse($privateKey, '测试固定私钥不可用');

        $signature = '';
        $this->assertTrue(openssl_sign($this->canonicalString($payload), $signature, $privateKey, OPENSSL_ALGO_SHA256));

        return base64_encode($signature);
    }

    public function test_valid_md5_signature_passes(): void
    {
        $payload = $this->notifyPayload();
        $payload['sign'] = $this->signMd5($payload);
        $payload['sign_type'] = 'MD5';

        $this->assertTrue($this->client()->verifyNotify($payload));
    }

    public function test_md5_signature_is_case_insensitive(): void
    {
        $payload = $this->notifyPayload();
        $payload['sign'] = strtoupper($this->signMd5($payload));
        $payload['sign_type'] = 'MD5';

        $this->assertTrue($this->client()->verifyNotify($payload));
    }

    public function test_tampered_md5_payload_fails(): void
    {
        $payload = $this->notifyPayload();
        $payload['sign'] = $this->signMd5($payload);
        $payload['sign_type'] = 'MD5';
        $payload['money'] = '0.01';

        $this->assertFalse($this->client()->verifyNotify($payload));
    }

    public function test_empty_merchant_key_fails_md5_verification(): void
    {
        $payload = $this->notifyPayload();
        $payload['sign'] = $this->signMd5($payload);
        $payload['sign_type'] = 'MD5';

        $this->assertFalse($this->client(['merchant_key' => ''])->verifyNotify($payload));
    }

    public function test_valid_rsa_signature_passes(): void
    {
        $payload = $this->notifyPayload();
        $payload['sign'] = $this->signRsa($payload);
        $payload['sign_type'] = 'RSA';

        $this->assertTrue($this->client(['sign_type' => 'RSA'])->verifyNotify($payload));
    }

    public function test_tampered_rsa_payload_fails(): void
    {
        $payload = $this->notifyPayload();
        $payload['sign'] = $this->signRsa($payload);
        $payload['sign_type'] = 'RSA';
        $payload['money'] = '0.01';

        $this->assertFalse($this->client(['sign_type' => 'RSA'])->verifyNotify($payload));
    }

    public function test_malformed_base64_rsa_signature_fails_without_throwing(): void
    {
        $payload = $this->notifyPayload();
        $payload['sign'] = '***not-base64***';
        $payload['sign_type'] = 'RSA';

        $this->assertFalse($this->client(['sign_type' => 'RSA'])->verifyNotify($payload));
    }

    public function test_missing_signature_fails(): void
    {
        $this->assertFalse($this->client()->verifyNotify($this->notifyPayload()));
    }

    public function test_unknown_sign_type_falls_back_to_md5(): void
    {
        $payload = $this->notifyPayload();
        $payload['sign'] = $this->signMd5($payload);
        $payload['sign_type'] = 'WEIRD';

        // 未声明的签名方式按 MD5 处理，与 normalizeSignType() 的回落口径一致
        $this->assertTrue($this->client()->verifyNotify($payload));
    }

    public function test_matches_merchant_id_rejects_blank_and_mismatched_values(): void
    {
        $client = $this->client();

        $this->assertTrue($client->matchesMerchantId(self::MERCHANT_ID));
        $this->assertFalse($client->matchesMerchantId('10002'));
        $this->assertFalse($client->matchesMerchantId(''));
        $this->assertFalse($client->matchesMerchantId(null));
        $this->assertFalse($this->client(['merchant_id' => ''])->matchesMerchantId(self::MERCHANT_ID));
    }
}
