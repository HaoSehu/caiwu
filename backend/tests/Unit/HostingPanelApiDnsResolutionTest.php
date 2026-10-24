<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 上游主机面板接口的 DNS 解析：纯 PHP 两级兜底、正负缓存与 SSRF 前置校验。
 *
 * 历史实现把解析放进 proc_open 子进程换取超时控制，宝塔默认禁用 proc_open 后
 * 该子进程必然失败并降级，等于白付一次进程创建成本，故收敛为纯 PHP 解析。
 */
class HostingPanelApiDnsResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'idc.hosting_panel_api.jwt_cache_store' => 'array',
        ]);
        Cache::flush();
    }

    public function test_ip_address_is_returned_without_resolution(): void
    {
        $addresses = $this->invoke('resolveHostAddresses', '203.0.113.10');

        $this->assertSame(['203.0.113.10'], $addresses);
    }

    public function test_local_test_host_bypasses_resolution(): void
    {
        // testing 环境对 .test 后缀直接返回环回地址，避免单测依赖外网 DNS
        $addresses = $this->invoke('resolveHostAddresses', 'finance.example.test');

        $this->assertSame(['127.0.0.1'], $addresses);
    }

    public function test_positive_cache_short_circuits_resolution(): void
    {
        Cache::put(
            'upstream:hosting_panel_api:dns:finance.example.com',
            ['198.51.100.7'],
            now()->addSeconds(60)
        );

        $addresses = $this->invoke('resolveHostAddresses', 'finance.example.com');

        $this->assertSame(['198.51.100.7'], $addresses);
    }

    public function test_negative_cache_throws_without_resolution(): void
    {
        Cache::put(
            'upstream:hosting_panel_api:dns:finance.example.com:failed',
            true,
            now()->addSeconds(60)
        );

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('无法解析供应商接口域名');

        $this->invoke('resolveHostAddresses', 'finance.example.com');
    }

    public function test_lookup_uses_hosts_file_fallback(): void
    {
        // gethostbynamel 读取 hosts，localhost 在任意环境都能解析
        $addresses = $this->invoke('lookupHostAddresses', 'localhost');

        $this->assertContains('127.0.0.1', $addresses);
    }

    public function test_unresolvable_host_throws_and_writes_negative_cache(): void
    {
        try {
            $this->invoke('resolveHostAddresses', 'no-such-host.invalid');
            $this->fail('解析失败时应抛出业务异常');
        } catch (BusinessException $exception) {
            $this->assertSame(42200, $exception->getErrorCode());
        }

        $this->assertTrue(
            Cache::get('upstream:hosting_panel_api:dns:no-such-host.invalid:failed'),
            '解析失败应写入负缓存，避免重复付出解析等待成本'
        );
    }

    /**
     * @return array<int, string>
     */
    private function invoke(string $method, string $host): array
    {
        $reflection = new ReflectionMethod(HostingPanelApiTransport::class, $method);
        $reflection->setAccessible(true);

        /** @var array<int, string> $result */
        $result = $reflection->invoke(new HostingPanelApiTransport, $host);

        return $result;
    }
}
