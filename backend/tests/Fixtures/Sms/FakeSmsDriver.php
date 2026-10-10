<?php

declare(strict_types=1);

namespace Tests\Fixtures\Sms;

use App\Services\Sms\Contracts\ProvidesVerifyCodeTemplate;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\Data\SmsMessageRequest;
use App\Services\Sms\Data\SmsSendRequest;
use App\Services\Sms\Data\SmsSendResult;
use RuntimeException;

/**
 * 共享短信驱动替身（V7-11 收敛：新测试不再写匿名替身）。
 *
 * - 默认 key = 'aliyun'（系统默认短信驱动键）；也可构造为任意键用于候选/绑定解析用例。
 * - sendMessage / sendVerifyCode 仅记录请求并返回可预期结果，不触网。
 * - $failWith 非空时两个发送入口都会抛出，用于失败路径用例。
 * - 实现 ProvidesVerifyCodeTemplate：驱动侧验证码文案（sendAliyunVerifyCode 依赖此能力）。
 */
final class FakeSmsDriver implements ProvidesVerifyCodeTemplate, SmsDriver
{
    /** @var list<SmsMessageRequest> */
    public array $messages = [];

    /** @var list<SmsSendRequest> */
    public array $verifyCodes = [];

    public function __construct(
        private readonly string $key = 'aliyun',
        private readonly string $label = '测试短信驱动',
        private readonly string $verifyTemplate = '验证码${code}，${min}分钟内有效。',
        public ?string $failWith = null,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function verifyCodeTemplate(string $purpose): string
    {
        return $this->verifyTemplate;
    }

    public function sendMessage(SmsMessageRequest $request): SmsSendResult
    {
        $this->guardFailure();
        $this->messages[] = $request;

        return new SmsSendResult(
            status: 'success',
            requestId: 'fake-msg-'.count($this->messages),
            templateCode: $request->templateCode,
        );
    }

    public function sendVerifyCode(SmsSendRequest $request): SmsSendResult
    {
        $this->guardFailure();
        $this->verifyCodes[] = $request;

        return new SmsSendResult(
            status: 'success',
            requestId: 'fake-code-'.count($this->verifyCodes),
            templateCode: (string) $request->option('template_code', ''),
            templateParams: ['code' => $request->code],
        );
    }

    private function guardFailure(): void
    {
        if ($this->failWith !== null) {
            throw new RuntimeException($this->failWith);
        }
    }
}
