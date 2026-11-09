<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\ConfigureTrustedProxies;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * 可信代理请求期配置（M-42 修复回归）。
 *
 * bootstrap/app.php 的 withMiddleware 回调在容器 make(HttpKernel) 时执行，
 * 此时 .env 尚未加载，env('TRUSTED_PROXIES') 恒空——修复前任何部署都配不上
 * 可信代理。中间件改为 handle 期读 config('app.trusted_proxies')。
 */
class FixTrustedProxyConfigTest extends TestCase
{
    private array $savedProxies = [];

    private int $savedHeaderSet = -1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedProxies = Request::getTrustedProxies();
        $this->savedHeaderSet = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        // 可信代理是 Symfony Request 的静态状态，不还原会泄漏到后续用例。
        Request::setTrustedProxies($this->savedProxies, $this->savedHeaderSet);

        parent::tearDown();
    }

    public function test_configured_proxies_are_trusted_for_forwarded_headers(): void
    {
        config(['app.trusted_proxies' => '198.51.100.10, 198.51.100.11']);

        $request = Request::create('/api/v2/client/invoices', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        $response = (new ConfigureTrustedProxies)->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame('ok', $response->getContent());
        $this->assertSame(['198.51.100.10', '198.51.100.11'], Request::getTrustedProxies());
        $this->assertSame(
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT,
            Request::getTrustedHeaderSet(),
        );

        // 端到端语义：受信反代转发的 XFF 才是真实客户端 IP。
        $this->assertSame('203.0.113.9', $request->ip());
    }

    public function test_config_array_is_supported(): void
    {
        config(['app.trusted_proxies' => ['10.0.0.5']]);

        $request = Request::create('/up', 'GET', server: [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.20',
        ]);

        (new ConfigureTrustedProxies)->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame(['10.0.0.5'], Request::getTrustedProxies());
        $this->assertSame('203.0.113.20', $request->ip());
    }

    public function test_empty_config_clears_residual_proxies(): void
    {
        // 模拟上一个请求（或队列 worker）留下的可信名单。
        Request::setTrustedProxies(['198.51.100.10'], Request::HEADER_X_FORWARDED_FOR);

        config(['app.trusted_proxies' => '']);

        $request = Request::create('/api/v2/client/invoices', 'GET', server: [
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ]);

        (new ConfigureTrustedProxies)->handle($request, static fn (): Response => new Response('ok'));

        $this->assertSame([], Request::getTrustedProxies());
        // 空配置下 XFF 不可信，ip() 必须回落到直连地址。
        $this->assertSame('198.51.100.10', $request->ip());
    }

    public function test_configured_proxies_from_config_are_read_at_handle_time(): void
    {
        // 回归点：env() 在 kernel 构建期读不到，config() 必须能读到。
        // phpunit 通过 <env> 注入的配置全部来自配置层，这里直接断言 config 通路。
        config(['app.trusted_proxies' => '192.0.2.7']);

        $request = Request::create('/up', 'GET', server: [
            'REMOTE_ADDR' => '192.0.2.7',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        (new ConfigureTrustedProxies)->handle($request, static fn (): Response => new Response('ok'));

        $this->assertTrue($request->isSecure());
    }
}
