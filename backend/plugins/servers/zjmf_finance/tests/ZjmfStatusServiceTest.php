<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Supplier;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfStatusService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/ZjmfFinanceTransport.php';
require_once __DIR__.'/../lib/ZjmfStatusService.php';

class ZjmfStatusServiceTest extends TestCase
{
    private function service(): ZjmfStatusService
    {
        return (new ReflectionClass(ZjmfStatusService::class))->newInstanceWithoutConstructor();
    }

    private function supplier(): Supplier
    {
        $supplier = new Supplier;
        $supplier->id = 9;

        return $supplier;
    }

    private function method(string $name): ReflectionMethod
    {
        $method = new ReflectionMethod(ZjmfStatusService::class, $name);
        $method->setAccessible(true);

        return $method;
    }

    public function test_unauthorized_payload_detection_covers_body_status(): void
    {
        $service = $this->service();

        $this->assertTrue($this->method('isUnauthorizedPayload')->invoke($service, ['status' => 401]));
        $this->assertTrue($this->method('isUnauthorizedPayload')->invoke($service, ['code' => 401]));
        $this->assertFalse($this->method('isUnauthorizedPayload')->invoke($service, ['status' => 200]));
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
