<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integrations\Mofang\Support\MofangCredentialParser;
use PHPUnit\Framework\TestCase;

class MofangCredentialParserTest extends TestCase
{
    public function test_it_extracts_mofang_zjmf_cookie_from_raw_notes(): void
    {
        $cookie = (new MofangCredentialParser)->parseWebSessionCookie(
            "接口登录不可用时使用\nZJMF_SESSION=abc123; ZJMF_TOKEN=def456\n不要外泄"
        );

        $this->assertSame('ZJMF_SESSION=abc123; ZJMF_TOKEN=def456', $cookie);
    }

    public function test_it_ignores_generic_cookie_notes(): void
    {
        $cookie = (new MofangCredentialParser)->parseWebSessionCookie(
            'web_session_cookie=PHPSESSID=abc123'
        );

        $this->assertSame('', $cookie);
    }
}
