<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Supplier;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Http;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 系统侧并发 POST 传输层：表单体序列化、JWT 认证头组装与 401 会话失效。
 */
class HostingPanelApiParallelPostTest extends TestCase
{
    private function supplier(): Supplier
    {
        $supplier = new Supplier;
        $supplier->id = 21;
        // .example.test 后缀在测试环境绕过 DNS 校验（SSRF 防护白名单）
        $supplier->api_url = 'https://finance.example.test';
        $supplier->api_username = 'test-user';
        $supplier->api_key = 'test-key';

        return $supplier;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['idc.hosting_panel_api.jwt_cache_store' => 'array']);
    }

    public function test_parallel_post_sends_form_encoded_bodies_with_jwt_auth(): void
    {
        Http::fake([
            'https://finance.example.test/provision/default*' => Http::response(['status' => 1001, 'data' => ['status' => 'on']]),
        ]);

        $transport = new HostingPanelApiTransport;
        $responses = $transport->parallelPost(
            $this->supplier(),
            [
                'runtime_1' => [
                    'uri' => '/provision/default',
                    'payload' => ['id' => 9, 'func' => 'status'],
                ],
            ],
            'jwt-abc'
        );

        $this->assertSame(200, $responses['runtime_1']['status_code'] ?? null);
        $this->assertSame('on', $responses['runtime_1']['response']['data']['status'] ?? null);

        $recorded = Http::recorded();
        $this->assertNotEmpty($recorded, '并发请求未被录制');
        $request = $recorded[0][0];
        $this->assertSame('https://finance.example.test/provision/default', $request->url());
        $this->assertSame('POST', $request->method());
        $this->assertSame('id=9&func=status', $request->body());
        // 系统侧认证头遵循上游 JWT 前缀约定，而非 Bearer
        $this->assertSame(['JWT jwt-abc'], $request->headers()['authorization'] ?? []);
    }

    public function test_parallel_post_401_forgets_cached_jwt(): void
    {
        Http::fake([
            'https://finance.example.test/provision/default*' => Http::response(['status' => 401], 401),
        ]);

        $transport = new HostingPanelApiTransport;
        $supplier = $this->supplier();

        // 预置一条 JWT 缓存（login 走原生 socket 无法 fake，直接验证 401 失效行为）
        $method = new ReflectionMethod(HostingPanelApiTransport::class, 'jwtCacheKey');
        $method->setAccessible(true);
        $cacheKey = $method->invoke($transport, $supplier);
        Cache::store('array')->put($cacheKey, 'jwt-old', 60);

        $responses = $transport->parallelPost(
            $supplier,
            [
                'runtime_1' => [
                    'uri' => '/provision/default',
                    'payload' => ['id' => 9, 'func' => 'status'],
                ],
            ],
            'jwt-abc'
        );

        $this->assertSame(401, $responses['runtime_1']['status_code'] ?? null);
        $this->assertNull(Cache::store('array')->get($cacheKey));
    }
}
