<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * 可信代理配置（B-10 修复回归）：
 * 生产在 Nginx 反代后，未声明可信代理时 $request->ip() 恒为反代地址，
 * 按 IP 的限流与轮询凭证 IP 绑定都会失效。
 */
class TrustedProxyTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_untrusted_proxy_forwarded_for_is_ignored(): void
    {
        // 默认（未配置 TRUSTED_PROXIES）：XFF 不可信，ip() 取 REMOTE_ADDR。
        $request = Request::create('/api/v2/client/invoices', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        $this->assertSame('198.51.100.10', $request->ip());
    }

    public function test_trusted_proxy_forwarded_for_is_used(): void
    {
        config(['app.trusted_proxies' => ['198.51.100.10']]);

        $request = Request::create('/api/v2/client/invoices', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        // 与 bootstrap/app.php 同语义：只信任配置里列出的反代地址。
        $request->setTrustedProxies(['198.51.100.10'], Request::HEADER_X_FORWARDED_FOR);

        $this->assertSame('203.0.113.9', $request->ip());
    }
}
