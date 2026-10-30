<?php

declare(strict_types=1);

namespace Tests\Unit;

use Caiwu\Plugins\Certification\BaiduFace\Logic\BaiduFace;
use PHPUnit\Framework\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../logic/BaiduFaceClient.php';
require_once __DIR__.'/../logic/BaiduFace.php';

/**
 * 百度人脸实名认证回跳契约。
 *
 * 百度 H5 方案是「后验结果查询」，回跳请求本身不承载可信签名，
 * 因此回跳必须永远放行、由控制器引导用户回前端查询认证结果。
 * 这条「永远放行」是被审查确认过的有意设计，用测试钉住，
 * 避免后人把它当成漏验签改成拒绝，从而打断用户在手机端的认证流程。
 *
 * 注意入参层级：插件运行时把 HTTP 参数包成 payload，
 * 插件收到的 $request['payload'] 形如 ['payload' => <扁平 HTTP 参数>, 'headers' => [...]]，
 * 所以这里必须双层构造；少一层会让插件读到空载荷、回跳静默降级成引导提示。
 */
class BaiduFaceCallbackTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $httpPayload
     * @return array<string, mixed>
     */
    private function callbackRequest(array $httpPayload): array
    {
        return [
            'action' => 'certification.verify_callback',
            'config' => ['api_key' => 'test-key', 'secret_key' => 'test-secret'],
            'payload' => [
                'payload' => $httpPayload,
                'headers' => [],
            ],
        ];
    }

    public function test_callback_with_certify_id_passes(): void
    {
        $response = (new BaiduFace)->execute($this->callbackRequest(['certify_id' => 'BAIDU-CERT-0001']));

        $this->assertTrue($response['success']);
        $this->assertTrue($response['data']['passed']);
        $this->assertSame('回跳参数已接收', $response['data']['message']);
        $this->assertSame(0, $response['data']['code']);
        $this->assertSame(200, $response['data']['http_status']);
    }

    public function test_callback_without_certify_id_still_passes_with_guidance(): void
    {
        // 百度回跳可能编码或丢弃 certify_id；此时仍放行并提示用户回前端查询结果，
        // 不能让用户在手机端直接看到「签名失败」这种内部错误
        $response = (new BaiduFace)->execute($this->callbackRequest([]));

        $this->assertTrue($response['success']);
        $this->assertTrue($response['data']['passed']);
        $this->assertSame('回跳参数未携带 certify_id，请返回前端页面继续查询认证结果', $response['data']['message']);
        $this->assertSame(0, $response['data']['code']);
        $this->assertSame(200, $response['data']['http_status']);
    }

    public function test_callback_falls_back_to_token_field(): void
    {
        // certify_id 缺失时按 token 兜底识别本次回跳
        $response = (new BaiduFace)->execute($this->callbackRequest(['token' => 'BAIDU-TOKEN-0001']));

        $this->assertTrue($response['data']['passed']);
        $this->assertSame('回跳参数已接收', $response['data']['message']);
    }

    public function test_blank_certify_id_is_treated_as_missing(): void
    {
        $response = (new BaiduFace)->execute($this->callbackRequest(['certify_id' => '   ']));

        $this->assertTrue($response['data']['passed']);
        $this->assertSame('回跳参数未携带 certify_id，请返回前端页面继续查询认证结果', $response['data']['message']);
    }

    public function test_flat_payload_without_wrapper_still_passes_with_guidance(): void
    {
        // 只传一层（漏掉运行时包装）时插件读到的载荷为空：仍然放行，
        // 但降级为引导提示——这条用例锁定「结构变化只会降级文案，不会变成拒绝」
        $response = (new BaiduFace)->execute([
            'action' => 'certification.verify_callback',
            'config' => [],
            'payload' => ['certify_id' => 'BAIDU-CERT-0001'],
        ]);

        $this->assertTrue($response['data']['passed']);
        $this->assertSame('回跳参数未携带 certify_id，请返回前端页面继续查询认证结果', $response['data']['message']);
    }

    public function test_unsupported_action_returns_failure_envelope(): void
    {
        $response = (new BaiduFace)->execute(['action' => 'certification.nope', 'payload' => [], 'config' => []]);

        $this->assertFalse($response['success']);
        $this->assertSame('Unsupported plugin action', $response['message']);
        $this->assertSame([], $response['data']);
    }

    public function test_fee_config_normalizes_charge_switch(): void
    {
        $response = (new BaiduFace)->execute([
            'action' => 'certification.fee_config',
            'payload' => [],
            'config' => ['charge_enabled' => '1', 'amount' => '2.00', 'free_attempts' => '3'],
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame(3, $response['data']['free_attempts']);
        $this->assertSame(2.0, $response['data']['retry_fee']);
        $this->assertTrue($response['data']['charge_enabled']);
    }

    public function test_fee_config_disabled_charge_zeroes_retry_fee(): void
    {
        $response = (new BaiduFace)->execute([
            'action' => 'certification.fee_config',
            'payload' => [],
            'config' => ['charge_enabled' => '0', 'amount' => '2.00', 'free_times' => '0'],
        ]);

        $this->assertFalse($response['data']['charge_enabled']);
        $this->assertSame(0.0, $response['data']['retry_fee']);
        $this->assertSame(2.0, $response['data']['amount']);
    }
}
