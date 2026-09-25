<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Provisioning\ProvisionService;
use App\Services\System\SettingService;
use App\Services\Upstream\Contracts\ProvidesProvisioning;
use App\Services\Upstream\Contracts\UpstreamDriver;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * D3A-03/W4-#3 回归：
 * - 内置驱动开通"部分成功"时，已获得的上游账单/实例标识持久化到 provision_data；
 * - 幂等回查口径与未决账单断言一致：绑定缺失时回退 provision_data['upstream_host_id']；
 * - 本地持有上游实例标识而回查失败（异常/非 200）时阻断开通并转人工，不再静默重开。
 */
class ProvisionUpstreamIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_PROVIDER_KEY = 'fake_provisioner';

    public function test_partial_success_identifiers_are_persisted_to_provision_data(): void
    {
        $service = Service::query()->create([
            'user_id' => User::factory()->create()->id,
            'product_id' => Product::query()->create([
                'product_type' => 'cloud_host',
                'status' => 1,
                'pricing' => ['monthly' => '35.00'],
            ])->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'provision_data' => ['created_from_order' => 'ORD-TEST'],
        ]);

        $provisionService = new ProvisionService(
            new ProviderResolver(new ProviderRegistry([])),
            app(SettingService::class),
        );

        $method = new ReflectionMethod(ProvisionService::class, 'persistPartialUpstreamProvisionIdentifiers');
        $method->invoke($provisionService, $service, 321, [85845, 85846], 77);

        $fresh = $service->fresh();
        $this->assertSame(321, (int) data_get($fresh->provision_data, 'upstream_invoice_id'));
        $this->assertSame(85845, (int) data_get($fresh->provision_data, 'upstream_host_id'));
        $this->assertSame([85845, 85846], array_map(intval(...), data_get($fresh->provision_data, 'upstream_host_ids', [])));
        // 标识同步写入传入实例，保证 submitUpstreamProvision 失败路径合并时可见
        $this->assertSame(321, (int) data_get($service->provision_data, 'upstream_invoice_id'));
    }

    public function test_reusable_upstream_service_id_falls_back_to_provision_data(): void
    {
        $service = Service::query()->create([
            'user_id' => User::factory()->create()->id,
            'product_id' => Product::query()->create([
                'product_type' => 'cloud_host',
                'status' => 1,
                'pricing' => ['monthly' => '35.00'],
            ])->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'provision_data' => ['upstream_host_id' => '85845'],
        ]);

        $provisionService = new ProvisionService(
            new ProviderResolver(new ProviderRegistry([])),
            app(SettingService::class),
        );

        $method = new ReflectionMethod(ProvisionService::class, 'resolveReusableServiceUpstreamServiceId');

        // D3A-03：与 assertNoUnresolvedUpstreamProvisionInvoice 同口径，绑定缺失时回退 provision_data
        $this->assertSame('85845', $method->invoke($provisionService, $service));

        // 无绑定且无 provision_data 兜底时保持 null
        $service->forceFill(['provision_data' => ['created_from_order' => 'ORD-TEST']])->save();
        $this->assertNull($method->invoke($provisionService, $service));
    }

    public function test_idempotent_lookup_failure_blocks_provision(): void
    {
        // W4-#3：本地持有 upstream_host_id（来自 provision_data 兜底），上游回查抛异常——
        // 修复前仅告警后继续清空购物车→加购→checkout（重复开通风险），修复后阻断并转人工。
        $driver = $this->fakeProvisionDriver();

        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $user = User::factory()->create();
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'provision_data' => ['upstream_host_id' => '85845'],
        ]);
        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => 'new',
            'amount' => 35.00,
            'quantity' => 1,
            'billing_cycle' => 'monthly',
            'status' => 1,
        ]);

        // 商品→供应商绑定链，让 provisionViaUpstream 能解析出供应商与 fake 驱动
        $supplier = Supplier::query()->create([
            'name' => '测试开通供应商-'.uniqid(),
            'code' => 'SUP-PV-'.uniqid(),
        ]);
        $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplier->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'status' => 1,
        ]);
        DB::table('product_upstream_bindings')->insert([
            'product_id' => $product->id,
            'supplier_plugin_binding_id' => $supplierBindingId,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'upstream_product_id' => 'p-'.uniqid(),
            'status' => 1,
        ]);

        $provisionService = new ProvisionService(
            new ProviderResolver(new ProviderRegistry([$driver])),
            app(SettingService::class),
        );

        $method = new ReflectionMethod(ProvisionService::class, 'provisionViaUpstream');

        try {
            $method->invoke($provisionService, $order, $service);
            $this->fail('幂等回查失败时应阻断开通');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('回查失败', $exception->getMessage());
            $this->assertStringContainsString('85845', $exception->getMessage());
        }

        // 未走到购物车 checkout（fake 驱动未记录任何调用）
        $this->assertSame(['login'], array_unique($driver->calls));
    }

    public function test_idempotent_lookup_non_200_blocks_provision(): void
    {
        // W4-#3 非 200 分支：login 成功但回查 get 返回非 200——同样无法确认上游实例状态，
        // 必须阻断开通；修复前该分支仅记录日志后继续清空购物车→checkout，存在重复开通风险。
        $driver = $this->fakeProvisionDriverWithLookupStatus(503);

        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $user = User::factory()->create();
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'provision_data' => ['upstream_host_id' => '85845'],
        ]);
        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => 'new',
            'amount' => 35.00,
            'quantity' => 1,
            'billing_cycle' => 'monthly',
            'status' => 1,
        ]);

        $supplier = Supplier::query()->create([
            'name' => '测试开通供应商-'.uniqid(),
            'code' => 'SUP-PV-'.uniqid(),
        ]);
        $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplier->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'status' => 1,
        ]);
        DB::table('product_upstream_bindings')->insert([
            'product_id' => $product->id,
            'supplier_plugin_binding_id' => $supplierBindingId,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'upstream_product_id' => 'p-'.uniqid(),
            'status' => 1,
        ]);

        $provisionService = new ProvisionService(
            new ProviderResolver(new ProviderRegistry([$driver])),
            app(SettingService::class),
        );

        $method = new ReflectionMethod(ProvisionService::class, 'provisionViaUpstream');

        try {
            $method->invoke($provisionService, $order, $service);
            $this->fail('幂等回查非 200 时应阻断开通');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('回查未确认', $exception->getMessage());
            $this->assertStringContainsString('85845', $exception->getMessage());
        }

        // 只发生 login + get（回查），未走到购物车 checkout
        $this->assertSame(['login', 'get'], $driver->calls);
    }

    /**
     * 内置路径假驱动（回查非 200 变体）：login 成功返回令牌，get 返回指定状态码。
     */
    private function fakeProvisionDriverWithLookupStatus(int $lookupStatus): object
    {
        return new class($lookupStatus) implements ProvidesProvisioning, UpstreamDriver
        {
            /** @var array<int, string> */
            public array $calls = [];

            public function __construct(private readonly int $lookupStatus) {}

            public function key(): string
            {
                return 'fake_provisioner';
            }

            public function label(): string
            {
                return '测试开通驱动';
            }

            public function capabilities(): array
            {
                return [ProvidesProvisioning::class];
            }

            public function supports(string $capability): bool
            {
                return $capability === ProvidesProvisioning::class;
            }

            public function resolve(string $capability): ?object
            {
                return $this->supports($capability) ? $this : null;
            }

            public function login(Supplier $supplier): string
            {
                $this->calls[] = 'login';

                return 'fake-jwt-token';
            }

            public function get(Supplier $supplier, string $path, string $jwt): array
            {
                $this->calls[] = 'get';

                return ['status' => $this->lookupStatus, 'data' => []];
            }
        };
    }

    /**
     * 内置路径假驱动：声明 ProvidesProvisioning 但不实现 provisionOrder，
     * login 恒抛异常模拟回查不可达。
     */
    private function fakeProvisionDriver(): object
    {
        return new class implements ProvidesProvisioning, UpstreamDriver
        {
            /** @var array<int, string> */
            public array $calls = [];

            public function key(): string
            {
                return 'fake_provisioner';
            }

            public function label(): string
            {
                return '测试开通驱动';
            }

            public function capabilities(): array
            {
                return [ProvidesProvisioning::class];
            }

            public function supports(string $capability): bool
            {
                return $capability === ProvidesProvisioning::class;
            }

            public function resolve(string $capability): ?object
            {
                return $this->supports($capability) ? $this : null;
            }

            public function login(Supplier $supplier): string
            {
                $this->calls[] = 'login';

                throw new \RuntimeException('模拟上游回查不可达');
            }
        };
    }

    private int $pluginId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // 注册 fake 插件行：provider_key → plugin_id 解析依赖 integration_plugins；
        // 插入发生在测试事务内，由 DatabaseTransactions 统一回滚，不做手动清理
        $existing = DB::table('integration_plugins')
            ->where('domain', PluginDomain::UPSTREAM)
            ->where('plugin_key', self::FAKE_PROVIDER_KEY)
            ->first(['id']);
        $this->pluginId = $existing !== null
            ? (int) $existing->id
            : (int) DB::table('integration_plugins')->insertGetId([
                'domain' => PluginDomain::UPSTREAM,
                'slug' => self::FAKE_PROVIDER_KEY,
                'plugin_key' => self::FAKE_PROVIDER_KEY,
                'name' => '测试开通插件',
                'entry_class' => 'Tests\\FakeProvisioner',
                'status' => 1,
            ]);
    }
}
