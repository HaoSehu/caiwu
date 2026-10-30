<?php

declare(strict_types=1);

namespace Tests\Unit;

use Caiwu\Plugins\Certification\Stay33\Logic\Stay33;
use PHPUnit\Framework\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../logic/Stay33Client.php';
require_once __DIR__.'/../logic/Stay33.php';

/**
 * Stay33 实名认证回调解签回归。
 *
 * 回调签名口径：HMAC-SHA256( canonicalPayload(payload, timestamp, nonce), callback_secret )，
 * canonicalPayload 会把 timestamp/nonce 注入载荷、递归按键名排序后做 JSON 编码。
 * 这里按同一口径在测试侧重算期望签名，锁定「任一环节漂移都会让真实回调被拒」的契约。
 */
class Stay33CallbackSignatureTest extends TestCase
{
    private const SECRET = 'stay33-callback-secret';

    /**
     * @param  array<string, mixed>  $payload
     */
    private function canonicalPayload(array $payload, string $timestamp, string $nonce): string
    {
        unset($payload['sign'], $payload['signature']);

        $payload['timestamp'] = $timestamp;
        $payload['nonce'] = $nonce;

        $this->ksortRecursive($payload);

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ksortRecursive(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'certify_id' => 'STAY33-CERT-0001',
            'status' => 'PASS',
            'name' => '张三',
            'detail' => ['score' => '99.5', 'channel' => 'face'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function request(
        array $payload,
        ?string $signingSecret = self::SECRET,
        ?int $timestamp = null,
        string $nonce = 'nonce-abc',
        ?string $configSecret = null,
    ): array {
        $timestampValue = (string) ($timestamp ?? time());
        $signature = hash_hmac(
            'sha256',
            $this->canonicalPayload($payload, $timestampValue, $nonce),
            (string) $signingSecret
        );

        return [
            'action' => 'certification.verify_callback',
            'config' => ['callback_secret' => $configSecret ?? $signingSecret],
            'payload' => [
                'headers' => [
                    'x-signature' => $signature,
                    'x-timestamp' => $timestampValue,
                    'x-nonce' => $nonce,
                ],
                'payload' => $payload,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function assertRejected(array $response): void
    {
        $this->assertTrue($response['success']);
        $this->assertFalse($response['data']['passed']);
        $this->assertSame(40001, $response['data']['code']);
        $this->assertSame(401, $response['data']['http_status']);
        $this->assertSame('签名验证失败', $response['data']['message']);
    }

    public function test_valid_signature_passes(): void
    {
        $response = (new Stay33)->execute($this->request($this->payload()));

        $this->assertTrue($response['data']['passed']);
        $this->assertSame(0, $response['data']['code']);
        $this->assertSame(200, $response['data']['http_status']);
        $this->assertSame('签名验证通过', $response['data']['message']);
        $this->assertSame('STAY33-CERT-0001|nonce-abc', $response['data']['replay_key']);
    }

    public function test_tampered_payload_is_rejected(): void
    {
        $request = $this->request($this->payload());
        $request['payload']['payload']['status'] = 'REJECT';

        $this->assertRejected((new Stay33)->execute($request));
    }

    public function test_wrong_secret_is_rejected(): void
    {
        // 用另一个密钥签名、配置里是正确密钥：两条签名串不一致必须拒绝，
        // 否则密钥轮换窗口内的旧签名回调会被误放行
        $this->assertRejected((new Stay33)->execute(
            $this->request($this->payload(), 'another-secret', null, 'nonce-abc', self::SECRET)
        ));
    }

    public function test_missing_callback_secret_is_rejected(): void
    {
        $request = $this->request($this->payload());
        $request['config'] = [];

        $this->assertRejected((new Stay33)->execute($request));
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        $this->assertRejected((new Stay33)->execute($this->request($this->payload(), self::SECRET, time() - 301)));
    }

    public function test_timestamp_within_window_passes(): void
    {
        // 时间窗是 300 秒，边界内仍应放行，避免跨秒抖动误拒
        $response = (new Stay33)->execute($this->request($this->payload(), self::SECRET, time() - 299));

        $this->assertTrue($response['data']['passed']);
    }

    public function test_missing_nonce_is_rejected(): void
    {
        $request = $this->request($this->payload());
        unset($request['payload']['headers']['x-nonce']);

        $this->assertRejected((new Stay33)->execute($request));
    }

    public function test_missing_certify_id_is_rejected(): void
    {
        $payload = $this->payload();
        unset($payload['certify_id']);

        $this->assertRejected((new Stay33)->execute($this->request($payload)));
    }

    public function test_nested_arrays_are_canonicalized_before_signing(): void
    {
        // 嵌套数组的键序不同但语义相同，签名应仍然有效：canonicalPayload 递归排序
        $payload = [
            'certify_id' => 'STAY33-CERT-0002',
            'detail' => ['channel' => 'face', 'score' => '88.0'],
            'status' => 'PASS',
        ];
        $reordered = [
            'status' => 'PASS',
            'detail' => ['score' => '88.0', 'channel' => 'face'],
            'certify_id' => 'STAY33-CERT-0002',
        ];

        $request = $this->request($payload);
        $request['payload']['payload'] = $reordered;

        $this->assertTrue((new Stay33)->execute($request)['data']['passed']);
    }

    public function test_percent_and_slash_in_payload_survive_canonicalization(): void
    {
        // JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES：中文与斜杠不转义，签名两侧必须一致
        $payload = [
            'certify_id' => 'STAY33-CERT-0003',
            'remark' => '通过/合格 100%',
        ];

        $response = (new Stay33)->execute($this->request($payload));

        $this->assertTrue($response['data']['passed']);
    }

    public function test_unsupported_action_returns_failure_envelope(): void
    {
        $response = (new Stay33)->execute(['action' => 'certification.nope', 'payload' => [], 'config' => []]);

        $this->assertFalse($response['success']);
        $this->assertSame('Unsupported plugin action', $response['message']);
        $this->assertSame([], $response['data']);
    }

    public function test_fee_config_normalizes_charge_switch(): void
    {
        $response = (new Stay33)->execute([
            'action' => 'certification.fee_config',
            'payload' => [],
            'config' => ['charge_enabled' => '1', 'amount' => '3.50', 'free_times' => '2'],
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame(2, $response['data']['free_attempts']);
        $this->assertSame(3.5, $response['data']['retry_fee']);
        $this->assertSame(3.5, $response['data']['amount']);
        $this->assertTrue($response['data']['charge_enabled']);
    }
}
