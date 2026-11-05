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
use Tests\TestCase;

/**
 * 驱动自行落库的检查点不得被开通编排层的回写覆盖。
 *
 * 事故链路（zjmf 上游）：用户在 upstream 下单时上游余额不足，结算已成功并建了
 * host/账单，但 `/apply_credit` 返回「余额不足」；插件在提交上游前已把
 * `downstream_token` 落库，失败回写却用进入上游窗口之前的旧 model 快照拼
 * provision_data 并整段覆盖，token 被抹掉。充值后重试生成新 token，上游按
 * token 匹配的 /cart/clear 幂等门失效，于是重新加购结算开出第二台同名主机。
 *
 * 这里用假驱动复刻「驱动在 provisionOrder 内写库后失败/成功」的契约，
 * 断言编排层回写必须以数据库最新值为基线。
 */
class ProvisionWritebackMergeTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_PROVIDER_KEY = 'fake_writeback_merge';

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
                'name' => '测试回写合并插件',
                'entry_class' => 'Tests\\FakeWritebackMerge',
                'status' => 1,
            ]);
    }

    public function test_failure_writeback_keeps_checkpoints_written_by_driver(): void
    {
        [$order, $service] = $this->makeBoundContext();
        $token = str_repeat('a', 32);

        $driver = $this->makeDriver(function (Service $passed) use ($token): void {
            $data = (array) ($passed->provision_data ?? []);
            $data['downstream_token'] = $token;
            $data['upstream_invoice_id'] = 77;
            $data['upstream_host_ids'] = [601];
            $data['upstream_host_id'] = 601;
            $passed->forceFill(['provision_data' => $data])->save();

            throw new BusinessException('余额不足：账单需35.00，现余额0.00');
        });

        try {
            $this->makeProvisionService($driver)->processPaidOrder($order);
            $this->fail('上游失败必须抛出');
        } catch (BusinessException) {
            // 预期路径
        }

        $data = (array) ($service->fresh()->provision_data ?? []);

        $this->assertSame($token, (string) ($data['downstream_token'] ?? ''), '失败回写不得冲掉驱动已落库的 downstream_token');
        $this->assertSame(77, (int) ($data['upstream_invoice_id'] ?? 0), '失败回写不得冲掉驱动已落库的账单标识');
        $this->assertSame(601, (int) ($data['upstream_host_id'] ?? 0), '失败回写不得冲掉驱动已落库的实例标识');
        $this->assertSame('余额不足：账单需35.00，现余额0.00', (string) ($data['provision_error'] ?? ''), '失败原因仍要写入');
    }

    public function test_success_writeback_keeps_checkpoints_written_by_driver(): void
    {
        [$order, $service] = $this->makeBoundContext();
        $token = str_repeat('b', 32);

        $driver = $this->makeDriver(function (Service $passed) use ($token): void {
            $data = (array) ($passed->provision_data ?? []);
            $data['downstream_token'] = $token;
            $passed->forceFill(['provision_data' => $data])->save();
        });

        $this->makeProvisionService($driver)->processPaidOrder($order);

        $fresh = $service->fresh();
        $this->assertSame($token, (string) data_get($fresh->provision_data, 'downstream_token'), '成功回写不得冲掉驱动已落库的 downstream_token');
        $this->assertSame(ServiceStatus::ACTIVE, (int) $fresh->status);
    }

    public function test_retry_reuses_token_persisted_before_failure(): void
    {
        [$order] = $this->makeBoundContext();

        $tokens = [];
        $attempt = function (Service $passed) use (&$tokens): void {
            $data = (array) ($passed->provision_data ?? []);
            $token = trim((string) ($data['downstream_token'] ?? ''));

            if ($token === '') {
                $token = bin2hex(random_bytes(16));
                $data['downstream_token'] = $token;
                $passed->forceFill(['provision_data' => $data])->save();
            }

            $tokens[] = $token;
        };

        try {
            $this->makeProvisionService($this->makeDriver(function (Service $passed) use ($attempt): void {
                $attempt($passed);

                throw new BusinessException('模拟上游余额不足');
            }))->processPaidOrder($order);
            $this->fail('首次开通必须失败才能进入重试场景');
        } catch (BusinessException) {
            // 预期路径
        }

        $this->makeProvisionService($this->makeDriver($attempt))
            ->retryFailedProvision(Order::query()->findOrFail($order->id));

        $this->assertCount(2, $tokens);
        $this->assertSame($tokens[0], $tokens[1], '重试必须沿用首次落库的 downstream_token，换 token 会让上游幂等门失效');
    }

    /**
     * @return array{0: Order, 1: Service}
     */
    private function makeBoundContext(): array
    {
        $user = User::factory()->create(['is_verified' => 1]);

        $supplier = Supplier::query()->create([
            'name' => '回写合并测试供应商-'.uniqid(),
            'code' => 'SUP-MERGE-'.uniqid(),
            'status' => 1,
        ]);

        $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplier->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'environment' => 'production',
            'status' => 1,
            'base_url' => 'https://merge-upstream.example.test',
            'account_name' => 'merge-account',
        ]);

        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'name' => '回写合并测试商品',
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
            'config_snapshot' => ['hostname' => 'merge-host.example.test'],
        ]);

        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'name' => '回写合并测试服务',
            'domain' => 'merge-host.example.test',
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

    private function makeDriver(?\Closure $onProvision): object
    {
        return new class($onProvision) implements ProvidesProvisioning, UpstreamDriver
        {
            public function __construct(private readonly mixed $onProvision) {}

            public function key(): string
            {
                return 'fake_writeback_merge';
            }

            public function label(): string
            {
                return '测试回写合并驱动';
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
                return 'fake-jwt';
            }

            public function get(Supplier $supplier, string $path, string $jwt): array
            {
                return ['status' => 200, 'data' => []];
            }

            public function provisionOrder(Order $order, Supplier $supplier, ?Service $service): array
            {
                if ($service instanceof Service && is_callable($this->onProvision)) {
                    ($this->onProvision)($service);
                }

                return [
                    'requested_host' => 'merge-host.example.test',
                    'upstream_invoice_id' => 88,
                    'upstream_host_ids' => [602],
                    'upstream_host_id' => 602,
                    'host_detail' => [
                        'id' => 602,
                        'domain' => 'merge-host.example.test',
                        'product_id' => 11,
                        'domainstatus' => 'Active',
                    ],
                ];
            }
        };
    }
}
