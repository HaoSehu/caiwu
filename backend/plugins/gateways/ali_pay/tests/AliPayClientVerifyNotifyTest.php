<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Integrations\Payments\Concerns\WrapsPemKeys;
use Caiwu\Plugins\Gateways\AliPay\Lib\AlipayClient;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/AlipayClient.php';

/**
 * 支付宝异步通知验签回归。
 *
 * 固定测试密钥对（2048 位 RSA，仅用于测试，不可用于任何真实环境）：
 * 私钥用于在测试里造签名，公钥即插件配置里的「支付宝公钥」。
 * 固定而非运行时生成——本机 PHP 未配置 openssl.cnf 时 openssl_pkey_new() 会失败，
 * 固定密钥让该用例在开发机与 CI 上有同一结果。
 */
class AliPayClientVerifyNotifyTest extends TestCase
{
    use WrapsPemKeys;

    private const PRIVATE_KEY = 'MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDOSXsieSbPXG1JKvgl4z/HpTm2kITCtBoMCKxXRdPJU5kNnOOd11+EVMrGUqPer7duLvK9LYIvV7lEM8yfdoDVQHOMEcdOOrGrYDXTyop5hm28deE1FHUaF6pmZW4V0z/CasvRjvSaeY03Jd5zujLGUaamawHMw7Zw7/9iWuKhsg+nvmAX3VWy09Eyo1iVMB/qTy0mWlRM3umMaQmEqQaBxPnv+msUBiB1ftC6O1KR5rsNAbHXuYDHwyjh0aKeZsXyyExxyo1Xkdh63y+mOJkjEHw6KfmQTTSEOLUTRExu5JgqUfE3iSXa3gYzwHa+dmARyta3LA/NyF/RX7G+1jlxAgMBAAECggEAAvvT7112ZtOU1BUDm6lZYTtIpf/vSlFTEB9UU3spKKAQK77RbwAau6it+dI1AxH3vVJUr1D4G/8GPmWLvTcNqX1MvoxSSEAilOfnwhK2T92BJajvKIv4HqK+06DTcmGIv6M4+U9lRn0MF8MGiut487PiInA+ntYqLnYriOISJZm4TNYIUbFG4CDa3vPdHXgCR8yw20QoUZqLA8uJlt/Sba5pRJx6bozce7hX90UVWfxMZRSzDTJLqaL2zhy3kH8ioJT6WLR+cbHbs78WJZrRo+zcIS0W2BGAt5Kda4i23z9NnX1PHsY3dPzX94c/cQM5o/vzv4XbAEQlCDjs7+t7kQKBgQD7M2qrYvw4hLhGxSc6ffLtLKqDh2HOBqY7vetidWY7pdzqbZkM7HVNMaShSdSafyvbR45VapHk1s9VtTEcbr1b1gj9zIuqxjQkKKHw6/6FLjvH9yRMH2BLiPzA2c13j8a9VFQM2NJbd/e1BHASxsfEUJfAsxlPR3vetti00A8QCQKBgQDSOmXkt7Bqjp3NgEIxT4lsrTjRsT3Rh5eY/NxMAPLn+vCallI7gsqWem4Ki188qCvFfraBEilKQ70KLf5v2RjCv28BIdWKogdJ4xL8a2v/GGl5IVE+2f/8NAQTJtxhEZJV3Ka+YXVwjoYvmKBzYYaB3KGXvoxdgzikM/4RBuFoKQKBgEAiFG3FS9eyKsoGs8k/NrJO4QnLmrDbCb3e8yWkC4HXCtvfN2ARTrFypzF2hA0dwritQC2Wk8D1BuI+7Fbx/u5fatRBULc8+zrwFMhJuCWQdDrNYjr9F7yxMLab5NVNo9Pan8rX4Hzx2BAjHentTjtUyCmukBupiHqH/DPNmsrhAoGBALlVIl7wUWafNB7m43P1WoGkdGLojkcZ8lu9D7kxXPOGFncx7mzocREX/crt85fV6kDB5MME78G41f8TbSvNiDan7YRh/4+oKCTLOwGs8hiBvPo8lRODvFjqwmzf5YjtpMRtMdLEuHPyMfnj5A/T+nChwRO1uKj39gdMjr8UAcOBAoGBAJn55iYPNSQPZIGgP0lKUrPVAxjzdLnKH6nQDuD0Nb6ClJZQ6+TJ6/C3AL7ZsLNWHKzFCgl2vS8Wh43lTcnPhU2AUdcxIQTvliNSbTDu4CjiUPYOiyqfnzCc/GdslkJVVYPQfrROJKro8XeC8sA6ja0nkQ0z958IDKs6jnYpShPJ';

    private const PUBLIC_KEY = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAzkl7Inkmz1xtSSr4JeM/x6U5tpCEwrQaDAisV0XTyVOZDZzjnddfhFTKxlKj3q+3bi7yvS2CL1e5RDPMn3aA1UBzjBHHTjqxq2A108qKeYZtvHXhNRR1GheqZmVuFdM/wmrL0Y70mnmNNyXec7oyxlGmpmsBzMO2cO//YlriobIPp75gF91VstPRMqNYlTAf6k8tJlpUTN7pjGkJhKkGgcT57/prFAYgdX7QujtSkea7DQGx17mAx8Mo4dGinmbF8shMccqNV5HYet8vpjiZIxB8Oin5kE00hDi1E0RMbuSYKlHxN4kl2t4GM8B2vnZgEcrWtywPzchf0V+xvtY5cQIDAQAB';

    private const APP_ID = '2021000000000000';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'http://api.example.test']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function client(array $overrides = []): AlipayClient
    {
        return new AlipayClient(array_merge([
            'app_id' => self::APP_ID,
            'private_key' => self::PRIVATE_KEY,
            'alipay_public_key' => self::PUBLIC_KEY,
            'alipay_enabled' => '1',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function stringToSign(array $params): string
    {
        unset($params['sign'], $params['sign_type']);
        ksort($params);

        return urldecode(http_build_query($params));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function signedParams(array $params, int $algorithm = OPENSSL_ALGO_SHA256): array
    {
        $privateKey = openssl_pkey_get_private($this->wrapPemKey(self::PRIVATE_KEY, 'PRIVATE KEY'));
        $this->assertNotFalse($privateKey, '测试固定私钥不可用');

        $signature = '';
        $this->assertTrue(openssl_sign($this->stringToSign($params), $signature, $privateKey, $algorithm));

        $params['sign'] = base64_encode($signature);
        $params['sign_type'] = $algorithm === OPENSSL_ALGO_SHA256 ? 'RSA2' : 'RSA';

        return $params;
    }

    /**
     * @return array<string, mixed>
     */
    private function notifyParams(): array
    {
        return [
            'app_id' => self::APP_ID,
            'out_trade_no' => 'INV202610260001',
            'trade_no' => '2026102622001440000000000001',
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '128.00',
            'gmt_payment' => '2026-10-26 10:00:00',
        ];
    }

    public function test_valid_rsa2_signature_passes(): void
    {
        $client = $this->client();

        $this->assertTrue($client->verifyNotify($this->signedParams($this->notifyParams())));
    }

    public function test_tampered_payload_fails(): void
    {
        $client = $this->client();
        $params = $this->signedParams($this->notifyParams());

        $params['total_amount'] = '0.01';

        $this->assertFalse($client->verifyNotify($params));
    }

    public function test_rsa_sha1_sign_type_is_accepted(): void
    {
        $client = $this->client();

        $params = $this->signedParams($this->notifyParams(), OPENSSL_ALGO_SHA1);

        $this->assertTrue($client->verifyNotify($params));
    }

    public function test_missing_signature_fails(): void
    {
        $client = $this->client();
        $params = $this->notifyParams();

        $this->assertFalse($client->verifyNotify($params));
    }

    public function test_empty_public_key_fails_without_throwing(): void
    {
        $client = $this->client(['alipay_public_key' => '']);

        $this->assertFalse($client->verifyNotify($this->signedParams($this->notifyParams())));
    }

    public function test_invalid_public_key_fails_without_throwing(): void
    {
        $client = $this->client(['alipay_public_key' => 'not-a-valid-key']);

        $this->assertFalse($client->verifyNotify($this->signedParams($this->notifyParams())));
    }

    public function test_is_enabled_requires_switch_app_id_and_private_key(): void
    {
        $this->assertTrue($this->client()->isEnabled());
        $this->assertFalse($this->client(['alipay_enabled' => '0'])->isEnabled());
        $this->assertFalse($this->client(['app_id' => ''])->isEnabled());
        $this->assertFalse($this->client(['private_key' => ''])->isEnabled());
    }

    public function test_matches_app_id_rejects_blank_and_mismatched_values(): void
    {
        $client = $this->client();

        $this->assertTrue($client->matchesAppId(self::APP_ID));
        $this->assertFalse($client->matchesAppId('2021999999999999'));
        $this->assertFalse($client->matchesAppId(''));
        $this->assertFalse($client->matchesAppId(null));
        // 商户号未配置时一律拒绝：避免换绑窗口内第三方商户的合法签名回调被放行。
        $this->assertFalse($this->client(['app_id' => ''])->matchesAppId(self::APP_ID));
    }
}
