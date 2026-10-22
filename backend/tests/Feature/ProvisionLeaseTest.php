<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Exceptions\ProvisionLeaseBusyException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceOverviewService;
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
 * FUL-1/FUL-4 履约租约回归：
 * - 租约是实例级第三道闸：订单锁只串行化「支付后同步履约」，失败补偿命令、
 *   人工重试与上游回调都能绕过它，并发提交同一实例会重复 checkout；
 * - 抢不到租约必须不改服务状态（markPending 之前就返回），否则会把正在开通的服务打回待开通；
 * - 上游 checkout 可能超过租约时长，过期后他人接管，旧执行者回写状态前必须自证令牌，
 *   但上游账单/实例标识是防重复下单的唯一凭据，仍要落库；
 * - 租约过期且累计尝试达上限时只打人工核实标记，绝不自动重试。
 */
class ProvisionLeaseTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_PROVIDER_KEY = 'fake_lease_provisioner';

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
                'name' => '测试履约租约插件',
                'entry_class' => 'Tests\\FakeLeaseProvisioner',
                'status' => 1,
            ]);
    }

    public function test_only_one_of_two_executors_can_acquire_an_active_lease(): void
    {
        [, $service] = $this->makeBoundContext();
        $provisionService = $this->makeProvisionService($this->makeDriver());

        $method = new ReflectionMethod(ProvisionService::class, 'acquireProvisionLease');

        $firstToken = $method->invoke($provisionService, Service::query()->findOrFail($service->id));
        $secondToken = $method->invoke($provisionService, Service::query()->findOrFail($service->id));

        $this->assertIsString($firstToken);
        $this->assertSame(48, strlen($firstToken), '租约令牌定长 48 字符，正好填满 char(48) 列');
        $this->assertNull($secondToken, '租约未过期时第二个执行者必须抢不到');

        $row = DB::table('services')->where('id', $service->id)->first(['provision_lease_token', 'provision_attempts']);
        $this->assertSame($firstToken, $row->provision_lease_token);
        $this->assertSame(1, (int) $row->provision_attempts);
    }

    public function test_expired_lease_can_be_taken_over_by_another_executor(): void
    {
        [, $service] = $this->makeBoundContext();
        $provisionService = $this->makeProvisionService($this->makeDriver());
        $method = new ReflectionMethod(ProvisionService::class, 'acquireProvisionLease');

        $firstToken = $method->invoke($provisionService, $service);
        $this->assertIsString($firstToken);

        // 模拟上一次执行者超时未释放：到期时间推到过去
        DB::table('services')->where('id', $service->id)->update([
            'provision_lease_until' => now()->subMinute()->format('Y-m-d H:i:s'),
        ]);

        $takeoverToken = $method->invoke($provisionService, Service::query()->findOrFail($service->id));

        $this->assertIsString($takeoverToken);
        $this->assertNotSame($firstToken, $takeoverToken, '过期接管必须换新令牌，旧执行者凭旧令牌无法回写');
        $this->assertSame(2, (int) DB::table('services')->where('id', $service->id)->value('provision_attempts'));
    }

    public function test_occupied_lease_throws_busy_exception_without_touching_upstream_or_status(): void
    {
        [$order, $service] = $this->makeBoundContext();
        $driver = $this->makeDriver();

        // 先设为非 PENDING 并保留原因，用来证明「抢租约失败」没有走 markPending
        $service->forceFill(['status' => ServiceStatus::SUSPENDED, 'suspended_reason' => '人工暂停中'])->save();
        DB::table('services')->where('id', $service->id)->update([
            'provision_lease_token' => 'held-by-other-executor',
            'provision_lease_until' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->invokeSubmit($this->makeProvisionService($driver), $order, Service::query()->findOrFail($service->id));
            $this->fail('租约被占用必须抛 ProvisionLeaseBusyException，不能静默当成已提交');
        } catch (ProvisionLeaseBusyException $exception) {
            $this->assertSame(ProvisionLeaseBusyException::MESSAGE, $exception->getMessage());
            // BusinessException 把 HTTP 状态塞进 Exception::getCode()，业务码单独取。
            $this->assertSame(409, $exception->getCode());
            $this->assertSame(40901, $exception->getErrorCode());
        }

        $this->assertSame([], $driver->calls, '租约被占用时不得触碰上游');
        $fresh = $service->fresh();
        $this->assertSame(ServiceStatus::SUSPENDED, (int) $fresh->status, '抢不到租约不能把服务打回待开通');
        $this->assertSame('人工暂停中', $fresh->suspended_reason);
        $this->assertSame('held-by-other-executor', $fresh->provision_lease_token, '占用者的租约不得被覆盖或清理');
    }

    public function test_successful_provision_releases_lease_and_writes_upstream_identifiers(): void
    {
        [$order, $service] = $this->makeBoundContext();
        $driver = $this->makeDriver();

        $result = $this->invokeSubmit($this->makeProvisionService($driver), $order, $service);

        $fresh = $result->fresh();
        $this->assertSame(ServiceStatus::ACTIVE, (int) $fresh->status);
        $this->assertNull($fresh->provision_lease_token, '成功后必须释放租约');
        $this->assertNull($fresh->provision_lease_until);
        $this->assertSame(1, (int) $fresh->provision_attempts);
        $this->assertSame(9001, (int) data_get($fresh->provision_data, 'upstream_invoice_id'));
        $this->assertSame(7001, (int) data_get($fresh->provision_data, 'upstream_host_id'));
        $this->assertContains('provisionOrder', $driver->calls);
    }

    public function test_failed_provision_releases_lease_and_records_error(): void
    {
        [$order, $service] = $this->makeBoundContext();
        $driver = $this->makeDriver(provisionException: new BusinessException('模拟上游开通失败'));

        try {
            $this->invokeSubmit($this->makeProvisionService($driver), $order, $service);
            $this->fail('throwOnFailure=true 时必须重抛');
        } catch (BusinessException $exception) {
            $this->assertSame('模拟上游开通失败', $exception->getMessage());
        }

        $fresh = $service->fresh();
        $this->assertSame(ServiceStatus::PENDING, (int) $fresh->status);
        $this->assertSame('模拟上游开通失败', $fresh->suspended_reason);
        $this->assertSame('模拟上游开通失败', data_get($fresh->provision_data, 'provision_error'));
        $this->assertNull($fresh->provision_lease_token, '失败后同样必须释放租约，让补偿命令可以接管');
        $this->assertNull($fresh->provision_lease_until);
    }

    public function test_lease_taken_over_during_checkout_skips_state_writeback_but_keeps_upstream_ids(): void
    {
        [$order, $service] = $this->makeBoundContext();

        // 模拟：上游 checkout 超过 180 秒，租约被另一个执行者接管后再返回成功
        $driver = $this->makeDriver(onProvision: function () use ($service): void {
            DB::table('services')->where('id', $service->id)->update([
                'provision_lease_token' => 'takeover-token',
                'provision_lease_until' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
            ]);
        });

        $result = $this->invokeSubmit($this->makeProvisionService($driver), $order, $service);

        $fresh = $result->fresh();
        $this->assertSame(ServiceStatus::PENDING, (int) $fresh->status, '旧执行者不得覆盖接管者正在开通的状态');
        $this->assertSame('takeover-token', $fresh->provision_lease_token, '接管者的租约不得被旧执行者清理');
        $this->assertSame(9001, (int) data_get($fresh->provision_data, 'upstream_invoice_id'), '上游账单标识仍必须落库');
        $this->assertSame(7001, (int) data_get($fresh->provision_data, 'upstream_host_id'), '上游实例标识仍必须落库');
    }

    public function test_retry_command_marks_lease_exhausted_services_without_retrying(): void
    {
        [$order, $service] = $this->makeBoundContext();
        $service->forceFill([
            'provision_data' => array_merge((array) $service->provision_data, [
                'provision_error' => '历史开通失败',
                'retry_count' => 0,
            ]),
        ])->save();
        DB::table('services')->where('id', $service->id)->update([
            'provision_lease_token' => 'stale-token',
            'provision_lease_until' => now()->subHour()->format('Y-m-d H:i:s'),
            'provision_attempts' => 3,
        ]);

        $this->artisan('provision:retry-failed')
            ->expectsOutputToContain('开通租约过期')
            ->assertExitCode(0);

        $fresh = $service->fresh();
        $this->assertNotNull(data_get($fresh->provision_data, 'lease_exhausted_at'), '必须写入人工核实标记');
        $this->assertSame('stale-token', data_get($fresh->provision_data, 'lease_exhausted_token'));
        $this->assertSame(3, (int) data_get($fresh->provision_data, 'lease_exhausted_attempts'));
        $this->assertSame(0, (int) data_get($fresh->provision_data, 'retry_count'), '打标不得触发自动重试');
    }

    /**
     * @return array{0: Order, 1: Service}
     */
    private function makeBoundContext(): array
    {
        $user = User::factory()->create(['is_verified' => 1]);

        $supplier = Supplier::query()->create([
            'name' => '测试租约供应商-'.uniqid(),
            'code' => 'SUP-LEASE-'.uniqid(),
            'status' => 1,
        ]);

        $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplier->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'status' => 1,
        ]);

        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'name' => '测试租约商品',
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
            'config_snapshot' => ['hostname' => 'lease-host.example.test'],
        ]);

        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'name' => '租约测试服务',
            'domain' => 'lease-host.example.test',
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::PENDING,
            'provision_data' => [],
        ]);

        $order->forceFill(['service_id' => $service->id])->save();

        return [$order->fresh(), $service];
    }

    private function makeProvisionService(object $driver): ProvisionService
    {
        return new ProvisionService(
            new ProviderResolver(new ProviderRegistry([$driver])),
            app(SettingService::class),
        );
    }

    /**
     * @param  (callable(): void)|null  $onProvision
     */
    private function makeDriver(?callable $onProvision = null, ?\Throwable $provisionException = null): object
    {
        return new class($onProvision, $provisionException) implements ProvidesProvisioning, UpstreamDriver
        {
            /** @var array<int, string> */
            public array $calls = [];

            public function __construct(
                private readonly mixed $onProvision,
                private readonly mixed $provisionException,
            ) {}

            public function key(): string
            {
                return 'fake_lease_provisioner';
            }

            public function label(): string
            {
                return '测试履约租约驱动';
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

                if (is_callable($this->onProvision)) {
                    ($this->onProvision)();
                }

                if ($this->provisionException instanceof \Throwable) {
                    throw $this->provisionException;
                }

                return [
                    'requested_host' => 'lease-host.example.test',
                    'upstream_invoice_id' => 9001,
                    'upstream_host_ids' => [7001],
                    'upstream_host_id' => 7001,
                    'host_detail' => [
                        'domain' => 'lease-host.example.test',
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

    public function test_lease_is_renewed_to_cart_lock_window_before_hitting_upstream(): void
    {
        [$order, $service] = $this->makeBoundContext();
        $leaseUntilAtUpstream = null;

        $driver = $this->makeDriver(onProvision: function () use ($service, &$leaseUntilAtUpstream): void {
            $leaseUntilAtUpstream = (string) DB::table('services')
                ->where('id', $service->id)
                ->value('provision_lease_until');
        });

        $this->invokeSubmit($this->makeProvisionService($driver), $order, $service);

        $this->assertNotSame('', $leaseUntilAtUpstream, '进入上游前必须仍持有租约');
        $leaseUntilTimestamp = (int) strtotime($leaseUntilAtUpstream);
        // 初始租约 180 秒远短于通用驱动清购物车→加购→checkout→fund→查实例的
        // 串行 HTTP 窗口；续期后必须覆盖到购物车锁同口径（默认 1260 秒）。
        $this->assertGreaterThan(
            now()->addSeconds(600)->getTimestamp(),
            $leaseUntilTimestamp,
            '进入上游前必须把租约续期到覆盖整个上游窗口，否则中途可被他人接管重复下单',
        );
        $this->assertLessThanOrEqual(
            now()->addSeconds(1500)->getTimestamp(),
            $leaseUntilTimestamp,
            '续期不得无限延长，口径应为购物车锁 TTL',
        );
    }

    public function test_retry_command_skips_busy_lease_without_burning_retry_quota(): void
    {
        [$order, $service] = $this->makeBoundContext();

        // 造一个「孤儿单」：PENDING + provision_error 非空 + 订单已付。租约被他人held。
        $service->forceFill([
            'provision_data' => array_merge((array) $service->provision_data, [
                'provision_error' => '历史开通失败',
                'retry_count' => 0,
            ]),
        ])->save();
        DB::table('services')->where('id', $service->id)->update([
            'provision_lease_token' => 'held-by-other-executor',
            'provision_lease_until' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
            'provision_attempts' => 1,
        ]);

        // 命令内部走真实容器解析的 ProvisionService，注入假驱动覆盖上游解析链。
        $driver = $this->makeDriver();
        $this->app->instance(ProvisionService::class, $this->makeProvisionService($driver));

        $this->artisan('provision:retry-failed', ['--execute' => true])
            ->expectsOutputToContain('正在由其他任务开通')
            ->assertExitCode(0);

        // 主机名规则解析会先 login+get 一次 productsconfig，这是重试准备阶段的读操作；
        // 关键是绝不能进入 provisionOrder（真正的下单）。
        $this->assertNotContains('provisionOrder', $driver->calls, '租约被占时不得向下游提交开通订单');
        $fresh = $service->fresh();
        $this->assertSame(0, (int) data_get($fresh->provision_data, 'retry_count'), '租约撞车不得消耗重试额度');
        $this->assertSame('held-by-other-executor', $fresh->provision_lease_token, '占用者的租约不得被清理');
    }

    public function test_lease_lost_before_upstream_throws_busy_without_touching_upstream(): void
    {
        [$order, $service] = $this->makeBoundContext();
        $driver = $this->makeDriver();

        // 人工暂停中的服务：续期失败若发生在 markPending 之后，会把它打回 PENDING
        // 并清空 suspended_reason，等于「什么都没做却改了业务状态」。
        $service->forceFill([
            'status' => ServiceStatus::SUSPENDED,
            'suspended_reason' => '人工暂停中',
        ])->save();

        // 抢到租约后、进入上游前被他人接管：renewProvisionLease 的影响行数为 0，
        // 必须立刻放弃，绝不能带着失效租约去 checkout。
        DB::table('services')->where('id', $service->id)->update([
            'provision_lease_token' => 'takeover-before-upstream',
            'provision_lease_until' => now()->addMinutes(30)->format('Y-m-d H:i:s'),
        ]);

        try {
            $this->invokeSubmit($this->makeProvisionService($driver), $order, Service::query()->findOrFail($service->id));
            $this->fail('租约在进入上游前失效必须抛 ProvisionLeaseBusyException');
        } catch (ProvisionLeaseBusyException) {
            // 预期路径
        }

        $this->assertNotContains('provisionOrder', $driver->calls, '租约失效后不得触碰上游');
        $fresh = $service->fresh();
        $this->assertSame('takeover-before-upstream', $fresh->provision_lease_token, '接管者的租约不得被覆盖或清理');
        $this->assertSame(ServiceStatus::SUSPENDED, (int) $fresh->status, '续期失败必须在 markPending 之前，不能改业务状态');
        $this->assertSame('人工暂停中', $fresh->suspended_reason, '续期失败不得清空暂停原因');
    }

    public function test_dry_run_executes_invoice_marking_but_not_orphan_retry(): void
    {
        // 账单打标是幂等的纯标记写入（无上游调用、无资金动作），默认就该执行；
        // dry-run 只拦孤儿单的上游重试。文案必须说清楚这一点，否则运维会以为
        // 「什么都没写」，反而错过资金风险标记。
        [$order, $service] = $this->makeBoundContext();
        $service->forceFill([
            'provision_data' => array_merge((array) $service->provision_data, [
                'provision_error' => '历史开通失败',
                'retry_count' => 0,
            ]),
        ])->save();

        $invoice = Invoice::query()->create([
            'invoice_no' => 'PL'.bin2hex(random_bytes(4)),
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'type' => 'new',
            'amount' => 35.00,
            'paid_amount' => 35.00,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'fulfillment_pending' => true,
            'fulfillment_pending_at' => now()->subHours(2),
        ]);

        $driver = $this->makeDriver();
        $this->app->instance(ProvisionService::class, $this->makeProvisionService($driver));

        $this->artisan('provision:retry-failed')
            ->expectsOutputToContain('账单打标已执行，孤儿单未自动重试')
            ->assertExitCode(0);

        $this->assertTrue((bool) $invoice->fresh()->requires_refund, 'dry-run 也必须执行账单打标');
        $this->assertNotContains('provisionOrder', $driver->calls, 'dry-run 不得触发孤儿单的上游重试');
        $this->assertSame(0, (int) data_get($service->fresh()->provision_data, 'retry_count'), 'dry-run 不得消耗重试额度');
    }

    public function test_retry_command_summary_separates_quota_and_lease_skips(): void
    {
        // 两条不同的跳过原因必须分别计数：一条已达上限、一条租约被占。
        // 混成一个 skipped 时运维看不出「是额度用完还是撞上了并发」。
        [$limitedOrder, $limitedService] = $this->makeBoundContext();
        $limitedService->forceFill([
            'provision_data' => array_merge((array) $limitedService->provision_data, [
                'provision_error' => '历史开通失败',
                'retry_count' => 3,
            ]),
        ])->save();

        [$busyOrder, $busyService] = $this->makeBoundContext();
        $busyService->forceFill([
            'provision_data' => array_merge((array) $busyService->provision_data, [
                'provision_error' => '历史开通失败',
                'retry_count' => 0,
            ]),
        ])->save();
        DB::table('services')->where('id', $busyService->id)->update([
            'provision_lease_token' => 'held-by-other-executor',
            'provision_lease_until' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
            'provision_attempts' => 1,
        ]);

        $driver = $this->makeDriver();
        $this->app->instance(ProvisionService::class, $this->makeProvisionService($driver));

        $this->artisan('provision:retry-failed', ['--execute' => true, '--limit' => 50])
            ->expectsOutputToContain('达上限 1，租约被占 1')
            ->assertExitCode(0);

        $this->assertNotContains('provisionOrder', $driver->calls, '两类跳过都不得触达上游下单');
        $this->assertSame(3, (int) data_get($limitedService->fresh()->provision_data, 'retry_count'), '达上限的服务不得再消耗额度');
    }

    public function test_retry_fails_before_touching_status_when_lease_is_held(): void
    {
        // 重试路径的状态污染回归：retryFailedProvision 会在提交前调
        // prepareServiceForProvisionRetry，把服务打回 PENDING 并清空 suspended_reason。
        // 租约必须在那之前抢到——否则管理员看到「重试失败」，人工暂停的服务却被
        // 改成了待开通，暂停原因也丢了。
        [$order, $service] = $this->makeBoundContext();
        $driver = $this->makeDriver();

        $service->forceFill([
            'status' => ServiceStatus::SUSPENDED,
            'suspended_reason' => '人工暂停中',
            'provision_data' => ['provision_error' => '历史开通失败'],
        ])->save();

        DB::table('services')->where('id', $service->id)->update([
            'provision_lease_token' => 'held-by-other-executor',
            'provision_lease_until' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
            'provision_attempts' => 1,
        ]);

        try {
            $this->makeProvisionService($driver)->retryFailedProvision(Order::query()->findOrFail($order->id));
            $this->fail('租约被占用时重试必须抛 ProvisionLeaseBusyException');
        } catch (ProvisionLeaseBusyException) {
            // 预期路径
        }

        $fresh = $service->fresh();
        $this->assertSame(ServiceStatus::SUSPENDED, (int) $fresh->status, '租约被占时不得把服务打回待开通');
        $this->assertSame('人工暂停中', (string) $fresh->suspended_reason, '租约被占时不得清空暂停原因');
        $this->assertSame('held-by-other-executor', (string) $fresh->provision_lease_token, '接管者的租约不得被覆盖或清理');
        $this->assertSame(1, (int) $fresh->provision_attempts, '未真正提交时不得累加开通尝试次数');
        $this->assertNotContains('provisionOrder', $driver->calls, '租约被占时不得触碰上游');
    }

    public function test_lease_is_released_when_pending_snapshot_fails_before_upstream(): void
    {
        // 租约泄漏回归：markPending 会写库、会走容器解析（概览缓存失效）。
        // 这一步抛异常时若租约还没进 finally，实例会被挂到租约自然过期（1260 秒），
        // 期间所有执行者只能收到 409。这里用抛出异常的概览服务模拟该失败。
        [$order, $service] = $this->makeBoundContext();
        $driver = $this->makeDriver();

        $failingOverview = new class extends ServiceOverviewService
        {
            public function __construct() {}

            public function forgetGroupedOverviewCache(int $userId): void
            {
                throw new \RuntimeException('模拟概览缓存失效失败');
            }
        };
        $this->app->instance(ServiceOverviewService::class, $failingOverview);

        try {
            $this->makeProvisionService($driver)->retryFailedProvision(Order::query()->findOrFail($order->id));
            $this->fail('进入上游前的任何失败都必须继续向上抛，不能吞掉');
        } catch (BusinessException $exception) {
            $this->assertSame('上游开通失败，请检查供应商配置或日志', $exception->getMessage());
        }

        $fresh = $service->fresh();
        $this->assertNull($fresh->provision_lease_token, '进入上游前失败也必须立即归还租约，不能挂到自然过期');
        $this->assertNull($fresh->provision_lease_until, '进入上游前失败也必须立即归还租约，不能挂到自然过期');
        $this->assertNotContains('provisionOrder', $driver->calls, '失败发生在进入上游之前时不得触碰上游');
    }

    public function test_retry_command_reports_lease_exhausted_services_for_manual_review(): void
    {
        // lease_exhausted_* 以前只写不读，是埋在 provision_data JSON 里的死数据。
        // 补偿命令必须把它变成可见清单：每轮列出待人工核实上游账单的实例。
        [$order, $service] = $this->makeBoundContext();

        $service->forceFill([
            'status' => ServiceStatus::SUSPENDED,
            'provision_data' => [
                'lease_exhausted_at' => '2026-10-01 00:00:00',
                'lease_exhausted_token' => 'stale-token',
                'lease_exhausted_attempts' => 3,
                'lease_exhausted_reason' => '租约过期且累计开通尝试达上限，需人工核实上游账单状态',
            ],
        ])->save();

        $this->artisan('provision:retry-failed')
            ->expectsOutputToContain('因租约耗尽待人工核实上游账单')
            ->expectsOutputToContain('租约过期且累计开通尝试达上限，需人工核实上游账单状态')
            ->assertExitCode(0);
    }

    private function invokeSubmit(ProvisionService $provisionService, Order $order, Service $service): Service
    {
        $method = new ReflectionMethod(ProvisionService::class, 'submitUpstreamProvision');
        $result = $method->invoke($provisionService, $order, $service, true);
        $this->assertInstanceOf(Service::class, $result);

        return $result;
    }
}
