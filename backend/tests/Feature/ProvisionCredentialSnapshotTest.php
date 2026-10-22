<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
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
 * FUL-3 履约凭据冻结回归：
 * - 首次提交上游前冻结 supplier_id / provider_key / upstream_base_url，重试必须打回同一上游；
 * - 后台改绑端点后，在途订单的重试仍用旧端点（否则会对着新供应商重复下单或查不到原账单）；
 * - 失败回写不得把快照冲掉，否则一次失败就让冻结失效；
 * - 存量在途订单没有快照时回退实时解析，不能被新逻辑卡死。
 */
class ProvisionCredentialSnapshotTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_PROVIDER_KEY = 'fake_snapshot_provisioner';

    private const OLD_BASE_URL = 'https://old-upstream.example.test';

    private int $pluginId = 0;

    protected function setUp(): void
    {
        parent::setUp();

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
                'name' => '测试履约凭据冻结插件',
                'entry_class' => 'Tests\\FakeSnapshotProvisioner',
                'status' => 1,
            ]);
    }

    public function test_process_paid_order_freezes_credential_snapshot_before_submit(): void
    {
        [$order, $service] = $this->makeBoundContext();
        $snapshotAtCall = null;

        $driver = $this->makeDriver(onProvision: function () use ($service, &$snapshotAtCall): void {
            $snapshotAtCall = data_get(Service::query()->find($service->id)?->provision_data, 'credential_snapshot');
        });

        $this->makeProvisionService($driver)->processPaidOrder($order);

        $this->assertIsArray($snapshotAtCall, '快照必须在提交上游之前落库，重试才有旧端点可用');
        $this->assertSame((int) $snapshotAtCall['supplier_id'], (int) data_get($service->fresh()->provision_data, 'credential_snapshot.supplier_id'));
        $this->assertSame(self::FAKE_PROVIDER_KEY, $snapshotAtCall['provider_key']);
        $this->assertSame(self::OLD_BASE_URL, $snapshotAtCall['upstream_base_url']);
        $this->assertNotSame('', (string) ($snapshotAtCall['frozen_at'] ?? ''));

        $this->assertSame([self::OLD_BASE_URL], $driver->provisionEndpoints);
    }

    public function test_snapshot_is_not_overwritten_after_successful_provision(): void
    {
        [$order, $service] = $this->makeBoundContext();

        $this->makeProvisionService($this->makeDriver())->processPaidOrder($order);

        $fresh = $service->fresh();
        $this->assertSame(ServiceStatus::ACTIVE, (int) $fresh->status);
        $snapshot = data_get($fresh->provision_data, 'credential_snapshot');
        $this->assertIsArray($snapshot);
        $this->assertSame(self::OLD_BASE_URL, $snapshot['upstream_base_url']);
        $this->assertSame(self::FAKE_PROVIDER_KEY, $snapshot['provider_key']);
    }

    public function test_failed_provision_keeps_credential_snapshot(): void
    {
        [$order, $service] = $this->makeBoundContext();
        $driver = $this->makeDriver(provisionException: new BusinessException('模拟上游开通失败'));

        try {
            $this->makeProvisionService($driver)->processPaidOrder($order);
            $this->fail('开通失败必须抛出，交回上层重试');
        } catch (BusinessException $exception) {
            $this->assertSame('模拟上游开通失败', $exception->getMessage());
        }

        $snapshot = data_get($service->fresh()->provision_data, 'credential_snapshot');
        $this->assertIsArray($snapshot, '失败回写不能把首次冻结的快照冲掉，否则重试会打回改绑后的新上游');
        $this->assertSame(self::OLD_BASE_URL, $snapshot['upstream_base_url']);
        $this->assertSame(self::FAKE_PROVIDER_KEY, $snapshot['provider_key']);
    }

    public function test_retry_reuses_frozen_endpoint_after_binding_change(): void
    {
        [$order, $service] = $this->makeBoundContext();

        // 首次提交失败，快照按旧端点冻结
        try {
            $this->makeProvisionService($this->makeDriver(provisionException: new BusinessException('首次开通失败')))
                ->processPaidOrder($order);
            $this->fail('首次开通必须失败才能进入重试场景');
        } catch (BusinessException) {
            // 预期路径
        }

        $this->assertSame(
            self::OLD_BASE_URL,
            (string) data_get($service->fresh()->provision_data, 'credential_snapshot.upstream_base_url'),
        );

        // 后台把供应商端点改到新地址
        $supplierId = (int) data_get($service->fresh()->provision_data, 'credential_snapshot.supplier_id');
        DB::table('supplier_plugin_bindings')
            ->where('supplier_id', $supplierId)
            ->update(['base_url' => 'https://new-upstream.example.test']);

        $retryDriver = $this->makeDriver();
        $this->makeProvisionService($retryDriver)->retryFailedProvision(Order::query()->findOrFail($order->id));

        $this->assertSame([self::OLD_BASE_URL], $retryDriver->provisionEndpoints, '重试必须打回首次冻结的旧端点');
        $this->assertSame(
            self::OLD_BASE_URL,
            (string) data_get($service->fresh()->provision_data, 'credential_snapshot.upstream_base_url'),
            '重试不得重写快照',
        );
    }

    public function test_retry_does_not_freeze_a_second_snapshot(): void
    {
        [$order, $service] = $this->makeBoundContext();

        try {
            $this->makeProvisionService($this->makeDriver(provisionException: new BusinessException('首次开通失败')))
                ->processPaidOrder($order);
            $this->fail('首次开通必须失败才能进入重试场景');
        } catch (BusinessException) {
            // 预期路径
        }

        $frozenAt = (string) data_get($service->fresh()->provision_data, 'credential_snapshot.frozen_at');
        $this->assertNotSame('', $frozenAt);

        $this->makeProvisionService($this->makeDriver())->retryFailedProvision(Order::query()->findOrFail($order->id));

        $this->assertSame(
            $frozenAt,
            (string) data_get($service->fresh()->provision_data, 'credential_snapshot.frozen_at'),
            '已有合法快照时不得重新冻结（frozen_at 不变）',
        );
    }

    public function test_legacy_service_without_snapshot_falls_back_to_live_resolution(): void
    {
        [, $service] = $this->makeBoundContext();

        $provisionService = $this->makeProvisionService($this->makeDriver());

        $snapshotMethod = new ReflectionMethod(ProvisionService::class, 'provisionCredentialSnapshot');
        $this->assertNull($snapshotMethod->invoke($provisionService, Service::query()->findOrFail($service->id)));

        $resolveMethod = new ReflectionMethod(ProvisionService::class, 'resolveProductSupplier');
        $supplier = $resolveMethod->invoke(
            $provisionService,
            Product::query()->findOrFail($service->product_id),
            Service::query()->findOrFail($service->id),
        );

        $this->assertInstanceOf(Supplier::class, $supplier, '存量在途订单没有快照时必须回退实时解析');
        $this->assertSame(self::OLD_BASE_URL, (string) $supplier->api_url);
        $this->assertSame(self::FAKE_PROVIDER_KEY, (string) $supplier->provider_key);
    }

    public function test_retry_uses_frozen_provider_driver_after_rebinding_to_another_provider(): void
    {
        [$order, $service] = $this->makeBoundContext();

        // 首次提交失败，快照把 provider_key 冻结在旧供应商上
        try {
            $this->makeProvisionService($this->makeDriver(provisionException: new BusinessException('首次开通失败')))
                ->processPaidOrder($order);
            $this->fail('首次开通必须失败才能进入重试场景');
        } catch (BusinessException) {
            // 预期路径
        }

        $this->assertSame(
            self::FAKE_PROVIDER_KEY,
            (string) data_get($service->fresh()->provision_data, 'credential_snapshot.provider_key'),
        );

        // 后台把商品整条绑定改到另一个供应商驱动：只改端点不算换驱动，这里连
        // provider_key 一起换，模拟「换了一家上游」。驱动类由 provider_key 决定。
        DB::table('supplier_plugin_bindings')
            ->where('supplier_id', (int) data_get($service->fresh()->provision_data, 'credential_snapshot.supplier_id'))
            ->update(['provider_key' => 'fake_live_provisioner']);
        DB::table('product_upstream_bindings')
            ->where('product_id', $service->product_id)
            ->update(['provider_key' => 'fake_live_provisioner']);

        $frozenDriver = $this->makeDriver();
        $liveDriver = $this->makeDriver(providerKey: 'fake_live_provisioner');

        $this->makeProvisionService([$frozenDriver, $liveDriver])
            ->retryFailedProvision(Order::query()->findOrFail($order->id));

        // 关键断言：在途重试必须继续用首次冻结的驱动类，否则会「旧端点 + 新驱动」
        // 混搭——新驱动可能用完全不同的协议和鉴权去调旧上游，必然失败或重复下单。
        $this->assertContains('provisionOrder', $frozenDriver->calls, '重试必须由快照冻结的驱动执行');
        $this->assertSame([], $liveDriver->calls, '换绑后的实时驱动不得接手在途订单');
        $this->assertSame(ServiceStatus::ACTIVE, (int) $service->fresh()->status);
    }

    public function test_should_auto_provision_uses_frozen_provider_after_rebinding(): void
    {
        // shouldAutoProvision 是赔付/重试链路最前面的门禁：它若仍按实时绑定判断
        // 「驱动是否支持自动开通」，换绑到不支持该能力的供应商后，在途订单会在
        // 走到快照之前就被判定「未接入自动开通上游」，快照形同废纸。
        [$order, $service] = $this->makeBoundContext();

        try {
            $this->makeProvisionService($this->makeDriver(provisionException: new BusinessException('首次开通失败')))
                ->processPaidOrder($order);
            $this->fail('首次开通必须失败才能进入重试场景');
        } catch (BusinessException) {
            // 预期路径
        }

        $this->assertSame(
            self::FAKE_PROVIDER_KEY,
            (string) data_get($service->fresh()->provision_data, 'credential_snapshot.provider_key'),
        );

        // 后台换绑到另一个供应商驱动——注册表里没有它，实时判定拿不到驱动类
        DB::table('supplier_plugin_bindings')
            ->where('supplier_id', (int) data_get($service->fresh()->provision_data, 'credential_snapshot.supplier_id'))
            ->update(['provider_key' => 'fake_unrelated_provisioner']);
        DB::table('product_upstream_bindings')
            ->where('product_id', $service->product_id)
            ->update(['provider_key' => 'fake_unrelated_provisioner']);

        $provisionService = $this->makeProvisionService($this->makeDriver());
        $method = new ReflectionMethod(ProvisionService::class, 'shouldAutoProvision');
        $product = Product::query()->findOrFail($service->product_id);
        $frozenService = Service::query()->findOrFail($service->id);

        $this->assertTrue(
            (bool) $method->invoke($provisionService, $product, $frozenService),
            '在途订单必须按冻结的驱动类判定，换绑后仍应允许重试',
        );
        $this->assertFalse(
            (bool) $method->invoke($provisionService, $product),
            '没有服务的实时判定场景仍应读当前绑定（新驱动未注册 → 不自动开通）',
        );
    }

    public function test_snapshot_freezes_upstream_product_id_used_for_retry_ordering(): void
    {
        [$order, $service] = $this->makeBoundContext();

        // 首次提交失败 → 快照冻结当时的商品号
        try {
            $this->makeProvisionService($this->makeDriver(provisionException: new BusinessException('首次开通失败')))
                ->processPaidOrder($order);
            $this->fail('首次开通必须失败才能进入重试场景');
        } catch (BusinessException) {
            // 预期路径
        }

        $this->assertSame(
            11,
            (int) data_get($service->fresh()->provision_data, 'credential_snapshot.upstream_product_id'),
            '快照必须冻结真正下单用的上游商品号',
        );

        // 后台把商品换绑到另一个上游商品
        DB::table('product_upstream_bindings')
            ->where('product_id', $service->product_id)
            ->update(['upstream_product_id' => '99']);

        $provisionService = $this->makeProvisionService($this->makeDriver());
        $method = new ReflectionMethod(ProvisionService::class, 'resolveUpstreamProductId');
        $product = Product::query()->findOrFail($service->product_id);
        $frozenService = Service::query()->findOrFail($service->id);

        $this->assertSame(
            11,
            (int) $method->invoke($provisionService, $product, $frozenService),
            '在途重试必须继续用冻结的商品号，否则会「旧端点 + 新商品号」下错单',
        );
        $this->assertSame(
            99,
            (int) $method->invoke($provisionService, $product),
            '没有服务的实时解析场景仍应读当前绑定',
        );
    }

    public function test_legacy_snapshot_is_completed_without_overwriting_frozen_endpoint(): void
    {
        [$order, $service] = $this->makeBoundContext();

        // 升级前冻结的存量快照：有供应商与旧端点，没有 upstream_product_id
        $supplierResolver = new ReflectionMethod(ProvisionService::class, 'resolveProductSupplier');
        $resolvedSupplier = $supplierResolver->invoke(
            $this->makeProvisionService($this->makeDriver()),
            Product::query()->findOrFail($service->product_id),
            Service::query()->findOrFail($service->id),
        );
        $this->assertInstanceOf(Supplier::class, $resolvedSupplier);
        $supplierId = (int) $resolvedSupplier->id;

        $service->forceFill([
            'provision_data' => [
                'credential_snapshot' => [
                    'supplier_id' => $supplierId,
                    'provider_key' => self::FAKE_PROVIDER_KEY,
                    'upstream_base_url' => self::OLD_BASE_URL,
                    'frozen_at' => '2026-01-01 00:00:00',
                ],
            ],
        ])->save();

        // 后台把端点改到新地址：补全缺失字段时不得顺手把旧端点改掉
        DB::table('supplier_plugin_bindings')
            ->where('supplier_id', $supplierId)
            ->update(['base_url' => 'https://new-upstream.example.test']);

        $provisionService = $this->makeProvisionService($this->makeDriver());
        $freeze = new ReflectionMethod(ProvisionService::class, 'freezeProvisionCredentialSnapshot');
        $freeze->invoke($provisionService, Order::query()->findOrFail($order->id), Service::query()->findOrFail($service->id));

        $snapshot = (array) data_get($service->fresh()->provision_data, 'credential_snapshot');
        $this->assertSame(11, (int) ($snapshot['upstream_product_id'] ?? 0), '存量快照要补全缺失的商品号');
        $this->assertSame(self::OLD_BASE_URL, (string) ($snapshot['upstream_base_url'] ?? ''), '已冻结的端点不得被新绑定覆盖');
        $this->assertSame('2026-01-01 00:00:00', (string) ($snapshot['frozen_at'] ?? ''), '补全不得改写冻结时间');
    }

    /**
     * @return array{0: Order, 1: Service}
     */
    private function makeBoundContext(): array
    {
        $user = User::factory()->create(['is_verified' => 1]);

        $supplier = Supplier::query()->create([
            'name' => '测试快照供应商-'.uniqid(),
            'code' => 'SUP-SNAP-'.uniqid(),
            'status' => 1,
        ]);

        $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplier->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'environment' => 'production',
            'status' => 1,
            'base_url' => self::OLD_BASE_URL,
            'account_name' => 'snapshot-account',
        ]);

        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'name' => '测试快照商品',
            'status' => 1,
            'stock' => -1,
            'auto_setup' => 1,
            'pricing' => ['monthly' => '35.00'],
            'config_options' => [],
        ]);

        DB::table('product_upstream_bindings')->insert([
            'product_id' => $product->id,
            'supplier_plugin_binding_id' => $supplierBindingId,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'upstream_product_id' => '11',
            'status' => 1,
        ]);

        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => 'new',
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'paid_amount' => 35.00,
            'quantity' => 1,
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
            'config_snapshot' => ['hostname' => 'snapshot-host.example.test'],
        ]);

        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'name' => '快照测试服务',
            'domain' => 'snapshot-host.example.test',
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::PENDING,
            'provision_data' => [],
        ]);

        $order->forceFill(['service_id' => $service->id])->save();

        return [$order->fresh(), $service];
    }

    /**
     * @param  object|array<int, object>  $drivers
     */
    private function makeProvisionService(object|array $drivers): ProvisionService
    {
        return new ProvisionService(
            new ProviderResolver(new ProviderRegistry(is_array($drivers) ? $drivers : [$drivers])),
            app(SettingService::class),
        );
    }

    /**
     * @param  (callable(): void)|null  $onProvision
     */
    private function makeDriver(
        ?callable $onProvision = null,
        ?\Throwable $provisionException = null,
        ?string $providerKey = null,
    ): object {
        return new class($onProvision, $provisionException, $providerKey) implements ProvidesProvisioning, UpstreamDriver
        {
            /** @var array<int, string> */
            public array $calls = [];

            /** @var array<int, string> */
            public array $provisionEndpoints = [];

            public function __construct(
                private readonly mixed $onProvision,
                private readonly mixed $provisionException,
                private readonly mixed $providerKey,
            ) {}

            public function key(): string
            {
                return is_string($this->providerKey) && $this->providerKey !== ''
                    ? $this->providerKey
                    : 'fake_snapshot_provisioner';
            }

            public function label(): string
            {
                return '测试履约凭据冻结驱动';
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

                return ['status' => 200, 'data' => []];
            }

            public function provisionOrder(Order $order, Supplier $supplier, ?Service $service): array
            {
                $this->calls[] = 'provisionOrder';
                $this->provisionEndpoints[] = (string) $supplier->api_url;

                if (is_callable($this->onProvision)) {
                    ($this->onProvision)();
                }

                if ($this->provisionException instanceof \Throwable) {
                    throw $this->provisionException;
                }

                return [
                    'requested_host' => 'snapshot-host.example.test',
                    'upstream_invoice_id' => 9101,
                    'upstream_host_ids' => [7101],
                    'upstream_host_id' => 7101,
                    'host_detail' => [
                        'domain' => 'snapshot-host.example.test',
                        'domainstatus' => 'Active',
                        'nextduedate' => now()->addMonth()->format('Y-m-d'),
                        'username' => 'root',
                        'password' => 'fake-secret',
                        'port' => 22,
                    ],
                ];
            }
        };
    }
}
