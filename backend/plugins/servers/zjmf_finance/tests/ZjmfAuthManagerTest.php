<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Models\Supplier;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfAuthManager;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/ZjmfAuthManager.php';

class ZjmfAuthManagerTest extends TestCase
{
    private function jwtWithExp(int $ttlSeconds): string
    {
        $payload = base64_encode((string) json_encode(['exp' => time() + $ttlSeconds]));

        return 'header.'.$payload.'.signature';
    }

    private function supplier(int $id): Supplier
    {
        $supplier = new Supplier;
        $supplier->id = $id;
        $supplier->api_username = 'test-user-'.$id;
        $supplier->api_key = 'test-key-'.$id;
        $supplier->api_url = 'https://finance.example.com';

        return $supplier;
    }

    private function manager(HostingPanelApiTransport $transport): ZjmfAuthManager
    {
        // 测试环境使用 array 缓存仓库，避免依赖真实 redis。
        config(['idc.hosting_panel_api.jwt_cache_store' => 'array']);
        Cache::flush();

        return new ZjmfAuthManager($transport);
    }

    private function transportReturning(string $jwt): HostingPanelApiTransport
    {
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->method('request')->willReturn(['jwt' => $jwt]);

        return $transport;
    }

    public function test_login_caches_jwt_and_hits_cache_without_second_login(): void
    {
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->expects($this->exactly(1))
            ->method('request')
            ->willReturn(['jwt' => $this->jwtWithExp(3600)]);

        $manager = $this->manager($transport);
        $supplier = $this->supplier(1);

        $first = $manager->login($supplier);
        $second = $manager->login($supplier);

        $this->assertSame($first, $second);
        $this->assertSame('', trim((string) Cache::get('upstream:zjmf_finance_api:refresh:1', '')));
    }

    public function test_login_throws_when_response_missing_jwt(): void
    {
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->method('request')->willReturn(['status' => 200]);

        $manager = $this->manager($transport);

        $this->expectException(BusinessException::class);
        $manager->login($this->supplier(1));
    }

    public function test_refresh_jwt_is_debounced_within_marker_ttl(): void
    {
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->expects($this->exactly(1))
            ->method('request')
            ->willReturn(['jwt' => $this->jwtWithExp(3600)]);

        $manager = $this->manager($transport);
        $supplier = $this->supplier(1);

        $manager->refreshJwt($supplier);
        // 防抖标记有效期内，再次刷新只命中缓存的 JWT，不再触发上游登录。
        $second = $manager->refreshJwt($supplier);

        $this->assertNotSame('', trim((string) $manager->login($supplier)));
        $this->assertSame($second, $manager->login($supplier));
    }

    public function test_refresh_jwt_force_bypasses_debounce(): void
    {
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->expects($this->exactly(2))
            ->method('request')
            ->willReturn(['jwt' => $this->jwtWithExp(3600)]);

        $manager = $this->manager($transport);
        $supplier = $this->supplier(1);

        $manager->refreshJwt($supplier);
        $manager->refreshJwt($supplier, force: true);
    }

    public function test_forget_clears_jwt_and_refresh_marker(): void
    {
        $transport = $this->transportReturning($this->jwtWithExp(3600));
        $manager = $this->manager($transport);
        $supplier = $this->supplier(1);

        $manager->refreshJwt($supplier);
        $this->assertNotSame('', trim((string) Cache::get('upstream:zjmf_finance_api:refresh:1', '')));

        $manager->forget($supplier);

        $this->assertNull(Cache::get('upstream:zjmf_finance_api:jwt:1'));
        $this->assertNull(Cache::get('upstream:zjmf_finance_api:refresh:1'));
    }

    public function test_forget_if_unauthorized_clears_cache_and_allows_relogin(): void
    {
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->expects($this->exactly(2))
            ->method('request')
            ->willReturn(['jwt' => $this->jwtWithExp(3600)]);

        $manager = $this->manager($transport);
        $supplier = $this->supplier(1);

        $manager->login($supplier);
        $manager->forgetIfUnauthorizedResponse($supplier, 401, ['status' => 401], $manager->login($supplier));

        // 401 失效后防抖标记也被清除，可立即重新登录。
        $manager->login($supplier);

        $this->assertNull(Cache::get('upstream:zjmf_finance_api:refresh:1'));
    }

    public function test_jwt_cache_is_isolated_per_supplier(): void
    {
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport->expects($this->exactly(2))
            ->method('request')
            ->willReturn(['jwt' => $this->jwtWithExp(3600)]);

        $manager = $this->manager($transport);
        $supplierA = $this->supplier(1);
        $supplierB = $this->supplier(2);

        $manager->login($supplierA);

        // 供应商 B 独立登录，A 的缓存不受影响。
        $manager->login($supplierB);
    }
}
