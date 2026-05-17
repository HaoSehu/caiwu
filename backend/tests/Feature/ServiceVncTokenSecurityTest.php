<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Service;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceResolverService;
use App\Services\ClientServiceConsole\ServiceTransformService;
use App\Services\ClientServiceConsole\ServiceVncService;
use App\Services\System\OperationLogService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ServiceVncTokenSecurityTest extends TestCase
{
    public function test_public_vnc_token_payload_does_not_consume_single_use_token(): void
    {
        Cache::put('vnc_token:test-token', [
            'service_id' => 12,
            'password' => 'secret-password',
            'username' => 'root',
            'target' => '10.0.0.8:5900',
            'single_use' => true,
        ], now()->addMinutes(5));

        $service = $this->makeVncService();

        $payload = $service->resolvePublicVncTokenPayload('test-token');

        $this->assertSame('test-token', $payload['token']);
        $this->assertSame(12, $payload['service_id']);
        $this->assertSame('/ws/vnc', $payload['relay_path']);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertTrue(Cache::has('vnc_token:test-token'));
    }

    public function test_single_use_vnc_token_is_consumed_when_relay_resolves_it(): void
    {
        Cache::put('vnc_token:test-token', [
            'service_id' => 12,
            'password' => 'secret-password',
            'username' => 'root',
            'target' => '10.0.0.8:5900',
            'single_use' => true,
        ], now()->addMinutes(5));

        $service = $this->makeVncService();

        $params = $service->resolveVncToken('test-token');

        $this->assertSame('secret-password', $params['password']);
        $this->assertFalse(Cache::has('vnc_token:test-token'));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('VNC 链接已过期或无效，请重新获取');

        $service->resolveVncToken('test-token');
    }

    public function test_admin_vnc_token_payload_is_reusable_within_ttl(): void
    {
        Cache::put('vnc_token:admin-token', [
            'service_id' => 34,
            'password' => 'admin-secret',
            'username' => 'administrator',
            'target' => '10.0.0.9:5900',
            'single_use' => false,
        ], now()->addMinutes(5));

        $service = $this->makeVncService();

        $firstPayload = $service->resolvePublicVncTokenPayload('admin-token');
        $secondPayload = $service->resolvePublicVncTokenPayload('admin-token');

        $this->assertSame(34, $firstPayload['service_id']);
        $this->assertSame(34, $secondPayload['service_id']);
        $this->assertSame('/ws/vnc', $secondPayload['relay_path']);
        $this->assertArrayNotHasKey('password', $secondPayload);
    }

    public function test_admin_vnc_token_can_be_resolved_multiple_times_by_relay(): void
    {
        Cache::put('vnc_token:admin-relay-token', [
            'service_id' => 56,
            'password' => 'relay-secret',
            'username' => 'administrator',
            'target' => '10.0.0.10:5900',
            'single_use' => false,
        ], now()->addMinutes(5));

        $service = $this->makeVncService();

        $firstParams = $service->resolveVncToken('admin-relay-token');
        $secondParams = $service->resolveVncToken('admin-relay-token');

        $this->assertSame('relay-secret', $firstParams['password']);
        $this->assertSame('relay-secret', $secondParams['password']);
        $this->assertTrue(Cache::has('vnc_token:admin-relay-token'));
    }

    public function test_service_transform_service_redacts_raw_remote_error_message(): void
    {
        $resolver = $this->createMock(ServiceResolverService::class);
        $resolver->method('resolveGroupedOverviewTypeValue')->willReturn('server');
        $resolver->method('resolveConsoleMode')->willReturn('default');

        $service = new Service([
            'id' => 99,
            'name' => '测试实例',
            'status' => 1,
            'billing_cycle' => 'monthly',
            'amount' => '19.90',
            'auto_renew' => 0,
            'provision_data' => [],
        ]);

        $transformService = new ServiceTransformService($resolver);
        $detail = $transformService->transformDetail(
            $service,
            null,
            'cURL error 28: Operation timed out after 10001 milliseconds for https://secret-supplier.example/v1/host'
        );

        $this->assertSame('上游状态同步超时，请稍后重试', data_get($detail, 'upstream.remote_error'));
        $this->assertStringNotContainsString('secret-supplier.example', (string) data_get($detail, 'upstream.remote_error'));
    }

    private function makeVncService(): ServiceVncService
    {
        return new ServiceVncService(
            $this->createMock(OperationLogService::class),
            $this->createMock(ServiceDetailService::class),
            $this->createMock(ServiceTransformService::class),
        );
    }
}
