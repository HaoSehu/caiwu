<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Sms\Data\SmsSendResult;
use App\Services\Sms\Drivers\AliyunSmsDriver;
use App\Services\Verification\Data\VerificationInitializeResult;
use App\Services\Verification\Data\VerificationScanUrlResult;
use App\Services\Verification\Data\VerificationStatusResult;
use App\Services\Verification\Drivers\Stay33Driver;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class IntegrationDtoTest extends TestCase
{
    public function test_identity_dtos_keep_legacy_array_shape_without_raw_payload(): void
    {
        $initialize = new VerificationInitializeResult(200, '请求成功', 'CERT123', ['raw' => 'hidden']);
        $scanUrl = new VerificationScanUrlResult(200, '请继续认证', 'https://example.test/verify', ['raw' => 'hidden']);
        $status = new VerificationStatusResult(1, '审核通过', ['raw' => 'hidden']);

        $this->assertSame(['status' => 200, 'msg' => '请求成功', 'certify_id' => 'CERT123'], $initialize->toArray());
        $this->assertSame(['status' => 200, 'msg' => '请继续认证', 'url' => 'https://example.test/verify'], $scanUrl->toArray());
        $this->assertSame(['status' => 1, 'msg' => '审核通过'], $status->toArray());
    }

    public function test_sms_result_keeps_legacy_array_shape_without_raw_payload(): void
    {
        $result = new SmsSendResult('success', 'REQ123', 'TPL001', ['code' => '123456'], ['raw' => 'hidden']);

        $this->assertSame([
            'status' => 'success',
            'request_id' => 'REQ123',
            'template_code' => 'TPL001',
            'template_params' => ['code' => '123456'],
        ], $result->toArray());
    }

    public function test_provider_english_errors_are_mapped_to_chinese_messages(): void
    {
        $this->assertSame(
            '短信发送失败，请稍后重试',
            $this->invokePrivate(new AliyunSmsDriver, 'resolveFailureMessage', ['InvalidAccessKeyId.NotFound'])
        );

        $this->assertSame(
            '短信余额不足',
            $this->invokePrivate(new AliyunSmsDriver, 'resolveFailureMessage', ['短信余额不足'])
        );

        $this->assertSame(
            '实名认证接口配置错误，请联系管理员',
            $this->invokePrivate(new Stay33Driver, 'safeProviderMessage', ['SYSTEM_ERROR', '实名认证接口配置错误，请联系管理员'])
        );
    }

    private function invokePrivate(object $instance, string $method, array $args): mixed
    {
        $reflection = new ReflectionClass($instance);
        $methodRef = $reflection->getMethod($method);
        $methodRef->setAccessible(true);

        return $methodRef->invokeArgs($instance, $args);
    }
}
