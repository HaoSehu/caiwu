<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Services\Sms\Data\SmsMessageRequest;
use App\Services\Sms\Data\SmsSendRequest;
use App\Services\Sms\SmsDriverManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\Sms\FakeSmsDriver;

/**
 * 共享短信替身（tests/Fixtures/Sms/FakeSmsDriver）与驱动注册表的行为锁定：
 * 默认键 aliyun 可解析、未知键报业务错误、发送记录与失败注入符合预期。
 *
 * V7-11 收敛：后续短信域测试直接复用该替身，不再写匿名驱动。
 */
final class SmsDriverManagerFakeDriverTest extends TestCase
{
    public function test_explicit_key_resolves_registered_fake_driver(): void
    {
        $fake = new FakeSmsDriver;
        $manager = new SmsDriverManager([$fake]);

        $this->assertSame($fake, $manager->resolve('aliyun'));
        $this->assertSame(
            [['value' => 'aliyun', 'label' => '测试短信驱动']],
            $manager->options()
        );
    }

    public function test_unknown_key_is_rejected_with_business_error(): void
    {
        $manager = new SmsDriverManager([new FakeSmsDriver]);

        try {
            $manager->resolve('not-registered');
            $this->fail('未注册的短信驱动键必须抛出业务异常');
        } catch (BusinessException $exception) {
            $this->assertSame(42200, $exception->getErrorCode());
            $this->assertStringContainsString('未注册', $exception->getMessage());
        }
    }

    public function test_fake_driver_records_requests_and_supports_failure_injection(): void
    {
        $fake = new FakeSmsDriver;

        $result = $fake->sendVerifyCode(new SmsSendRequest('13800000000', '123456'));
        $this->assertSame('success', $result->status);
        $this->assertSame('123456', $result->templateParams['code'] ?? null);
        $this->assertCount(1, $fake->verifyCodes);
        $this->assertSame('验证码123456，5分钟内有效。', str_replace(
            ['${code}', '${min}'],
            ['123456', '5'],
            $fake->verifyCodeTemplate('login')
        ));

        $fake->sendMessage(new SmsMessageRequest('13800000000', 'TPL-1', '内容'));
        $this->assertCount(1, $fake->messages);

        $fake->failWith = '模拟通道故障';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('模拟通道故障');
        $fake->sendVerifyCode(new SmsSendRequest('13800000000', '654321'));
    }
}
