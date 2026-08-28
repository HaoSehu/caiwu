<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Supplier;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfAuthManager;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfStatusService;
use ReflectionMethod;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/ZjmfAuthManager.php';
require_once __DIR__.'/../lib/ZjmfFinanceTransport.php';
require_once __DIR__.'/../lib/ZjmfStatusService.php';

class ZjmfStatusServiceTest extends TestCase
{
    private function supplier(): Supplier
    {
        $supplier = new Supplier;
        $supplier->id = 9;

        return $supplier;
    }

    private function service(): ZjmfStatusService
    {
        return new ZjmfStatusService(new ZjmfFinanceTransport(
            $this->createMock(HostingPanelApiTransport::class),
            new ZjmfAuthManager($this->createMock(HostingPanelApiTransport::class)),
        ));
    }

    private function method(string $name): ReflectionMethod
    {
        $method = new ReflectionMethod(ZjmfStatusService::class, $name);
        $method->setAccessible(true);

        return $method;
    }

    public function test_runtime_requests_built_per_service_with_valid_host(): void
    {
        $service = $this->service();

        $requests = $this->method('buildRuntimeRequests')->invoke($service, [
            ['service_id' => 1, 'host_id' => 9],
            ['service_id' => 2, 'host_id' => 0],
            ['service_id' => 0, 'host_id' => 9],
        ]);

        $this->assertSame([
            'uri' => '/provision/default',
            'payload' => ['id' => 9, 'func' => 'status'],
        ], $requests['runtime_1'] ?? null);
        $this->assertCount(1, $requests);
    }

    public function test_sync_service_statuses_fetches_runtime_in_parallel(): void
    {
        $platform = $this->createMock(HostingPanelApiTransport::class);
        $platform->method('request')->willReturn(['status' => 200, 'jwt' => 'jwt-1']);
        $platform->method('parallelGet')->willReturn([
            'detail_7' => [
                'status_code' => 200,
                'response' => ['status' => 200, 'data' => ['host_data' => ['id' => 9, 'domainstatus' => 'Active']]],
                'error' => '',
                'content_type' => 'application/json',
            ],
        ]);
        $platform->expects($this->exactly(1))->method('parallelPost')->willReturn([
            'runtime_7' => [
                'status_code' => 200,
                'response' => ['status' => 1001, 'data' => ['status' => 'on', 'des' => 'ok']],
                'error' => '',
                'content_type' => 'application/json',
            ],
        ]);

        $transport = new ZjmfFinanceTransport($platform, new ZjmfAuthManager($platform));
        $service = new ZjmfStatusService($transport);
        $result = $service->syncServiceStatuses($this->supplier(), [['service_id' => 7, 'host_id' => 9]], 10);

        $this->assertSame('on', $result['services'][7]['runtime']['status'] ?? null);
        $this->assertSame('Active', $result['services'][7]['host']['domainstatus'] ?? null);
    }

    public function test_sync_service_statuses_retries_runtime_batch_with_fresh_jwt_on_401(): void
    {
        $platform = $this->createMock(HostingPanelApiTransport::class);
        $platform->method('request')->willReturn(['status' => 200, 'jwt' => 'jwt-1']);
        $platform->method('parallelGet')->willReturn([
            'detail_7' => [
                'status_code' => 200,
                'response' => ['status' => 200, 'data' => ['host_data' => ['id' => 9, 'domainstatus' => 'Active']]],
                'error' => '',
                'content_type' => 'application/json',
            ],
        ]);
        $platform->expects($this->exactly(2))->method('parallelPost')->willReturnOnConsecutiveCalls(
            [
                'runtime_7' => [
                    'status_code' => 401,
                    'response' => ['status' => 401],
                    'error' => '',
                    'content_type' => 'application/json',
                ],
            ],
            [
                'runtime_7' => [
                    'status_code' => 200,
                    'response' => ['status' => 1001, 'data' => ['status' => 'on']],
                    'error' => '',
                    'content_type' => 'application/json',
                ],
            ],
        );

        $transport = new ZjmfFinanceTransport($platform, new ZjmfAuthManager($platform));
        $service = new ZjmfStatusService($transport);
        $result = $service->syncServiceStatuses($this->supplier(), [['service_id' => 7, 'host_id' => 9]], 10);

        $this->assertSame('on', $result['services'][7]['runtime']['status'] ?? null);
    }

    public function test_runtime_unavailable_degraded_for_suspended_without_operation_message(): void
    {
        $service = $this->service();
        $host = ['domainstatus' => 'Active'];

        // 非预期失败（如网络错误）不降级，保持异常上抛。
        $context = $this->method('resolveRuntimeUnavailableContext')->invoke(
            $service,
            ['status_code' => 500, 'response' => ['msg' => 'system error']],
            $host
        );

        $this->assertNull($context);
    }

    public function test_runtime_unavailable_degrades_on_operation_not_allowed(): void
    {
        $service = $this->service();
        $host = ['domainstatus' => 'Active'];

        $context = $this->method('resolveRuntimeUnavailableContext')->invoke(
            $service,
            ['status_code' => 200, 'response' => ['msg' => '当前状态不允许执行该操作']],
            $host
        );

        $this->assertSame('operation_not_allowed', $context['reason'] ?? null);
    }

    public function test_runtime_unavailable_degrades_for_host_missing_after_termination(): void
    {
        $service = $this->service();
        $host = ['domainstatus' => 'cancelled'];

        $context = $this->method('resolveRuntimeUnavailableContext')->invoke(
            $service,
            ['status_code' => 404, 'response' => ['msg' => '主机不存在']],
            $host
        );

        $this->assertSame('host_missing_after_termination', $context['reason'] ?? null);
    }

    public function test_runtime_unavailable_keeps_throwing_for_http_error_on_active_host(): void
    {
        $service = $this->service();
        $host = ['domainstatus' => 'Active'];

        $context = $this->method('resolveRuntimeUnavailableContext')->invoke(
            $service,
            ['status_code' => 500, 'response' => ['msg' => '主机不存在']],
            $host
        );

        $this->assertNull($context);
    }
}
