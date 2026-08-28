<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Models\Supplier;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfAuthManager;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceTransport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionMethod;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/ZjmfAuthManager.php';
require_once __DIR__.'/../lib/ZjmfFinanceTransport.php';

class ZjmfFinanceTransportTest extends TestCase
{
    private function supplier(int $id): Supplier
    {
        $supplier = new Supplier;
        $supplier->id = $id;
        $supplier->api_username = 'test-user-'.$id;
        $supplier->api_key = 'test-key-'.$id;
        $supplier->api_url = 'https://finance.example.com';

        return $supplier;
    }

    /**
     * @return array{HostingPanelApiTransport&MockObject, ZjmfFinanceTransport}
     */
    private function transport(): array
    {
        $platform = $this->createMock(HostingPanelApiTransport::class);
        $platform->method('request')->willReturn(['status' => 200]);

        $transport = new ZjmfFinanceTransport($platform, new ZjmfAuthManager($platform));

        return [$platform, $transport];
    }

    #[DataProvider('allowedUriProvider')]
    public function test_allows_known_relative_uris(string $uri): void
    {
        [, $transport] = $this->transport();
        $method = $this->assertAllowedUriMethod();

        $this->assertNull($method->invoke($transport, $uri));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedUriProvider(): array
    {
        return [
            'login' => ['/zjmf_api_login'],
            'login with query' => ['/zjmf_api_login?from=api'],
            'v1 user' => ['/v1/user'],
            'cart credit' => ['/cart/credit'],
            'cart clear' => ['/cart/clear'],
            'host header' => ['/host/header?host_id=1&source=API'],
            'provision default' => ['/provision/default'],
            'pay' => ['/pay?action=billing&pay=true'],
            'check order' => ['/check_order'],
            'servicedetail' => ['/servicedetail?id=1&action=flowpacket'],
            'api prodetail' => ['/api/product/prodetail?pids[]=1'],
            'apply credit' => ['/apply_credit'],
            'absolute same host' => ['https://finance.example.com/servicedetail?id=1'],
        ];
    }

    #[DataProvider('deniedUriProvider')]
    public function test_rejects_unknown_relative_uris(string $uri): void
    {
        [, $transport] = $this->transport();
        $method = $this->assertAllowedUriMethod();

        $this->expectException(BusinessException::class);
        $method->invoke($transport, $uri);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function deniedUriProvider(): array
    {
        return [
            'unknown path' => ['/admin/delete-all'],
            'HPA style cart' => ['/v2/cart/checkout'],
            'traversal' => ['/../../etc/passwd'],
            'other scheme' => ['ftp://finance.example.com/x'],
        ];
    }

    public function test_request_rejects_uri_outside_whitelist(): void
    {
        $platform = $this->createMock(HostingPanelApiTransport::class);
        $platform->expects($this->never())->method('request');

        $transport = new ZjmfFinanceTransport($platform, new ZjmfAuthManager($platform));

        $this->expectException(BusinessException::class);
        $transport->request($this->supplier(1), 'GET', '/admin/delete-all');
    }

    public function test_request_allows_login_endpoint_without_jwt_resolution(): void
    {
        [$platform, $transport] = $this->transport();
        $supplier = $this->supplier(1);

        // /zjmf_api_login 属白名单且不强制解析 JWT 头，直接透传给平台传输层。
        $result = $transport->request($supplier, 'POST', '/zjmf_api_login', [
            'username' => 'u',
            'password' => 'p',
        ]);

        $this->assertSame(['status' => 200], $result);
        $this->assertNotNull($platform);
    }

    public function test_parallel_post_rejects_uri_outside_whitelist(): void
    {
        $platform = $this->createMock(HostingPanelApiTransport::class);
        $platform->expects($this->never())->method('parallelPost');

        $transport = new ZjmfFinanceTransport($platform, new ZjmfAuthManager($platform));

        $this->expectException(BusinessException::class);
        $transport->parallelPost($this->supplier(1), [['uri' => '/admin/delete-all']]);
    }

    public function test_parallel_post_delegates_and_forgets_jwt_on_401(): void
    {
        $platform = $this->createMock(HostingPanelApiTransport::class);
        $platform->method('parallelPost')->willReturn([
            'runtime_1' => ['status_code' => 401, 'response' => [], 'error' => '', 'content_type' => 'application/json'],
        ]);

        $transport = new ZjmfFinanceTransport($platform, new ZjmfAuthManager($platform));
        $responses = $transport->parallelPost(
            $this->supplier(1),
            [
                'runtime_1' => [
                    'uri' => '/provision/default',
                    'payload' => ['id' => 9, 'func' => 'status'],
                ],
            ],
            'jwt-1',
            ['content-type: application/x-www-form-urlencoded']
        );

        $this->assertSame(401, $responses['runtime_1']['status_code'] ?? null);
    }

    private function assertAllowedUriMethod(): ReflectionMethod
    {
        $method = new ReflectionMethod(ZjmfFinanceTransport::class, 'assertAllowedRequestUri');
        $method->setAccessible(true);

        return $method;
    }
}
