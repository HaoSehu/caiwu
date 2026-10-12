<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServicePowerService;
use App\Services\ClientServiceConsole\ServiceTransformService;
use App\Services\System\OperationLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

/**
 * 电源状态查询（实例状态=电源状态）回归：
 * - 归属校验：findUserService 走 ServiceDetailService，其他用户服务 404；
 * - 软契约：上游 runtime 未声明 getPowerStatus 时按"不支持电源状态查询"拒绝；
 * - 正向：runtime 返回 power_status/power_label 原样透出。
 */
class ServicePowerStatusApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_returns_power_status_from_runtime(): void
    {
        [$user, $service] = $this->fixture();

        $powerService = $this->makePowerService(new FakePowerRuntime([
            'power_status' => 'on',
            'power_label' => '开机',
        ]));

        $result = $powerService->getPowerStatusForUser($user, (int) $service->id);

        $this->assertSame(['service_id' => (int) $service->id, 'power_status' => 'on', 'power_label' => '开机'], $result);
    }

    public function test_rejects_when_runtime_lacks_power_status_capability(): void
    {
        [$user, $service] = $this->fixture();

        $powerService = $this->makePowerService(new FakePowerlessRuntime);

        try {
            $powerService->getPowerStatusForUser($user, (int) $service->id);
            $this->fail('未声明 getPowerStatus 能力的上游未被拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame(42200, $exception->getErrorCode());
            $this->assertSame('当前上游类型不支持电源状态查询', $exception->getMessage());
        }
    }

    public function test_rejects_other_users_service(): void
    {
        [$user, $service] = $this->fixture();
        $other = User::factory()->create();

        $powerService = $this->makePowerService(new FakePowerRuntime([
            'power_status' => 'on',
            'power_label' => '开机',
        ]));

        try {
            $powerService->getPowerStatusForUser($other, (int) $service->id);
            $this->fail('他人服务未被归属校验拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(40400, $exception->getErrorCode());
            $this->assertSame('服务不存在', $exception->getMessage());
        }
    }

    // ── Fixture ──────────────────────────────────────────────────────────

    /**
     * @return array{0: User, 1: Service}
     */
    private function fixture(): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => 1,
            'expires_at' => now()->addMonth(),
        ]);

        return [$user, $service];
    }

    private function makePowerService(object $runtime): ServicePowerService
    {
        $supplier = Supplier::query()->create([
            'name' => '测试供应商',
            'code' => 'power-status-test-'.bin2hex(random_bytes(4)),
            'status' => 1,
        ]);

        /** @var ServiceDetailService&Mockery\MockInterface $detailService */
        $detailService = Mockery::mock(ServiceDetailService::class);
        $detailService->shouldReceive('findUserService')->andReturnUsing(
            function (User $user, int $serviceId): Service {
                $service = Service::query()->where('user_id', $user->id)->find($serviceId);
                throw_if(! $service, new BusinessException('服务不存在', 40400, 404));

                return $service;
            }
        );
        $detailService->shouldReceive('resolveUpstreamContext')->andReturnUsing(
            fn (Service $service): array => [$runtime, $supplier, 77, 'jwt-1']
        );

        return new ServicePowerService(
            app(OperationLogService::class),
            $detailService,
            app(ServiceTransformService::class),
        );
    }
}

/**
 * 电源状态上游替身：声明 getPowerStatus 能力并返回固定载荷。
 */
final class FakePowerRuntime
{
    public function __construct(private readonly array $powerStatusPayload) {}

    public function login(Supplier $supplier): string
    {
        return 'jwt-1';
    }

    public function getPowerStatus(Supplier $supplier, int $hostId, string $jwt): array
    {
        return $this->powerStatusPayload;
    }
}

/**
 * 无电源能力上游替身：未声明 getPowerStatus，走软契约拒绝分支。
 */
final class FakePowerlessRuntime
{
    public function login(Supplier $supplier): string
    {
        return 'jwt-1';
    }
}
