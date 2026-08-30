<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AuditParamRedactor;
use PHPUnit\Framework\TestCase;

/**
 * 审计日志凭据剔除规则：密码族/令牌族全模块剔除；验证码仅在 auth 模块剔除
 * （业务模块的 code 是编码类业务标识，管理端审计需要完整展示）。
 */
class AuditParamRedactorTest extends TestCase
{
    public function test_auth_module_redacts_passwords_code_and_captcha(): void
    {
        $redacted = AuditParamRedactor::redact([
            'account' => 'user@example.test',
            'password' => 'secret123',
            'oldPassword' => 'a',
            'newPassword' => 'b',
            'code' => '123456',
            'captcha' => 'geetest-token',
        ], 'auth');

        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('[REDACTED]', $redacted['oldPassword']);
        $this->assertSame('[REDACTED]', $redacted['newPassword']);
        $this->assertSame('[REDACTED]', $redacted['code']);
        $this->assertSame('[REDACTED]', $redacted['captcha']);
        $this->assertSame('user@example.test', $redacted['account']);
    }

    public function test_non_auth_module_keeps_business_code_but_redacts_password(): void
    {
        $redacted = AuditParamRedactor::redact([
            'password' => 'secret',
            'code' => 'CAT-001',
        ], 'product');

        $this->assertSame('[REDACTED]', $redacted['password']);
        $this->assertSame('CAT-001', $redacted['code']);
    }

    public function test_tokens_are_redacted_in_every_module(): void
    {
        $redacted = AuditParamRedactor::redact([
            'token' => 't',
            'access_token' => 'at',
            'refresh_token' => 'rt',
        ]);

        $this->assertSame('[REDACTED]', $redacted['token']);
        $this->assertSame('[REDACTED]', $redacted['access_token']);
        $this->assertSame('[REDACTED]', $redacted['refresh_token']);
    }

    public function test_nested_arrays_are_redacted(): void
    {
        $redacted = AuditParamRedactor::redact([
            'user' => ['password' => 's', 'name' => 'n'],
            'items' => [['token' => 't']],
        ]);

        $this->assertSame('[REDACTED]', $redacted['user']['password']);
        $this->assertSame('n', $redacted['user']['name']);
        $this->assertSame('[REDACTED]', $redacted['items'][0]['token']);
    }

    public function test_key_matching_is_case_insensitive(): void
    {
        $redacted = AuditParamRedactor::redact(['Password' => 's', 'ACCESS_TOKEN' => 't'], '');

        $this->assertSame('[REDACTED]', $redacted['Password']);
        $this->assertSame('[REDACTED]', $redacted['ACCESS_TOKEN']);
    }
}
