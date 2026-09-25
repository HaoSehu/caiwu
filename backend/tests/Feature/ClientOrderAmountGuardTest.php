<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceTrafficPackageService;
use App\Services\ClientServiceConsole\ServiceUpgradeService;
use App\Services\Finance\InvoiceService;
use App\Services\System\OperationLogService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

/**
 * D4A-02 回归护栏：升降级/流量包下单金额必须有下限（>0），
 * 上游缺价或报价为 0 时显式报错，禁止生成 0 元账单免费履约。
 */
class ClientOrderAmountGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_upgrade_invoice_rejected_when_upstream_amount_is_zero(): void
    {
        [$user, $service] = $this->upgradeFixture();
        $upgradeService = $this->makeUpgradeService(['amount_total' => '0.00']);

        try {
            $upgradeService->createInvoiceForUser($user, (int) $service->id, [
                'product_id' => 2,
                'billing_cycle' => 'monthly',
            ]);
            $this->fail('0 元升降级报价未被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(42200, $exception->getErrorCode());
            $this->assertSame('升降级报价金额无效，暂无法创建账单', $exception->getMessage());
        }

        $this->assertSame(0, Invoice::query()->where('service_id', (int) $service->id)->count());
    }

    public function test_upgrade_invoice_rejected_when_upstream_quote_has_no_amount(): void
    {
        [$user, $service] = $this->upgradeFixture();
        $upgradeService = $this->makeUpgradeService(['name' => '目标套餐']);

        try {
            $upgradeService->createInvoiceForUser($user, (int) $service->id, [
                'product_id' => 2,
                'billing_cycle' => 'monthly',
            ]);
            $this->fail('上游缺价报价未被拦截');
        } catch (BusinessException $exception) {
            $this->assertSame(42200, $exception->getErrorCode());
            $this->assertSame('上游未返回有效的升降级报价，无法创建账单', $exception->getMessage());
        }

        $this->assertSame(0, Invoice::query()->where('service_id', (int) $service->id)->count());
    }

    /**
     * 报价金额解析支持多候选键兜底（amount_total 并非全上游通用），
     * 正常金额原样返回。
     */
    public function test_upgrade_quote_amount_resolves_from_fallback_candidates(): void
    {
        $method = new \ReflectionMethod(ServiceUpgradeService::class, 'resolveUpstreamQuoteAmount');
        $method->setAccessible(true);
        $instance = (new \ReflectionClass(ServiceUpgradeService::class))->newInstanceWithoutConstructor();

        $this->assertSame(12.5, $method->invoke($instance, ['amount_total' => '12.5']));
        $this->assertSame(8.0, $method->invoke($instance, ['total' => '8.00']));
        $this->assertSame(5.0, $method->invoke($instance, ['subtotal' => 5]));
    }

    /**
     * 流量包目录构建时过滤 0 价/缺价档位（upgradeconfig 模式），
     * 0 价档位不可售、不展示。
     */
    public function test_traffic_packages_filter_zero_price_slots(): void
    {
        $method = new \ReflectionMethod(ServiceTrafficPackageService::class, 'buildAvailableTrafficPackages');
        $method->setAccessible(true);
        $instance = (new \ReflectionClass(ServiceTrafficPackageService::class))->newInstanceWithoutConstructor();

        $option = ['id' => 9, 'field' => 'flow_limit', 'name' => '流量', 'sub' => [
            ['id' => 11, 'option_name_first' => '100G'],
            ['id' => 22, 'option_name_first' => '200G'],
        ]];
        $configured = [
            ['target_value' => 100, 'price' => '10.00', 'enabled' => 1],
            ['target_value' => 200, 'price' => '0.00', 'enabled' => 1],
        ];

        $packages = $method->invoke($instance, $configured, $option, ['bwlimit' => 0]);

        $this->assertCount(1, $packages);
        $this->assertSame(100, $packages[0]['target_value']);
        $this->assertSame('10.00', $packages[0]['price']);
    }

    /**
     * 流量包目录构建时过滤 0 价/缺价档位（flowpacket 模式），
     * 上游有档位但目录未配价格时同样不可售。
     */
    public function test_flow_packet_packages_filter_zero_price_slots(): void
    {
        $method = new \ReflectionMethod(ServiceTrafficPackageService::class, 'buildAvailableFlowPacketPackages');
        $method->setAccessible(true);
        $instance = (new \ReflectionClass(ServiceTrafficPackageService::class))->newInstanceWithoutConstructor();

        $configured = [
            ['target_value' => 100, 'price' => '9.90', 'enabled' => 1],
            ['target_value' => 200, 'price' => null, 'enabled' => 1],
        ];
        $flowPackets = [
            ['target_value' => 100, 'flow_packet_id' => 7],
            ['target_value' => 200, 'flow_packet_id' => 8],
        ];

        $packages = $method->invoke($instance, $configured, $flowPackets, ['bwlimit' => 0]);

        $this->assertCount(1, $packages);
        $this->assertSame(100, $packages[0]['target_value']);
        $this->assertSame('9.90', $packages[0]['price']);
    }

    // ── Fixture ──────────────────────────────────────────────────────────

    /**
     * @return array{0: User, 1: Service}
     */
    private function upgradeFixture(): array
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
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        return [$user, $service];
    }

    /**
     * 构造带上游报价替身的升降级服务：previewHostUpgrade 返回注入的报价载荷。
     *
     * @param  array<string, mixed>  $quotePayload
     */
    private function makeUpgradeService(array $quotePayload): ServiceUpgradeService
    {
        $catalog = new FakeUpgradeCatalog;
        $catalog->quotePayload = $quotePayload;

        /** @var ServiceDetailService&Mockery\MockInterface $detailService */
        $detailService = Mockery::mock(ServiceDetailService::class);
        $detailService->shouldReceive('findUserService')->andReturnUsing(
            fn (User $user, int $serviceId): Service => Service::query()->where('user_id', $user->id)->findOrFail($serviceId)
        );
        $detailService->shouldReceive('resolveUpstreamContext')->andReturn([$catalog, new Supplier, 777, 'jwt-1']);
        $detailService->shouldReceive('assertSuccess')->andReturnNull();
        $detailService->shouldReceive('extractPayload')->andReturnUsing(
            static fn (array $response): array => is_array($response['data'] ?? null) ? $response['data'] : $response
        );

        return new ServiceUpgradeService(
            $detailService,
            app(InvoiceService::class),
            app(OperationLogService::class),
        );
    }
}

/**
 * 升降级上游目录替身：仅实现报价预览，返回预置载荷。
 */
final class FakeUpgradeCatalog
{
    /** @var array<string, mixed> */
    public array $quotePayload = [];

    /**
     * @return array<string, mixed>
     */
    public function previewHostUpgrade(Supplier $supplier, int $hostId, int $productId, string $billingCycle, ?string $jwt = null): array
    {
        return ['status' => 200, 'data' => $this->quotePayload];
    }
}
