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
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfAuthManager;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfProvisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

require_once __DIR__.'/../../plugins/servers/zjmf_finance/lib/ZjmfFinanceTransport.php';
require_once __DIR__.'/../../plugins/servers/zjmf_finance/lib/ZjmfAuthManager.php';
require_once __DIR__.'/../../plugins/servers/zjmf_finance/lib/ZjmfUpstreamResponse.php';
require_once __DIR__.'/../../plugins/servers/zjmf_finance/lib/ZjmfCartPayloadBuilder.php';
require_once __DIR__.'/../../plugins/servers/zjmf_finance/lib/ZjmfProvisionService.php';

/**
 * ZJMF 开通检查点跨失败重试的回归（真实插件链路）：
 *
 * 事故：上游余额不足导致「结算成功、支付失败」。插件此前只在支付成功后才落
 * provision_data，失败回写又用旧 model 快照整段覆盖，于是 downstream_token /
 * upstream_invoice_id / upstream_host_id 全部丢失。充值后重试生成新 token，
 * 上游按 token 匹配的幂等门失效，重新加购结算开出第二台同名主机。
 *
 * 本用例固定三条契约：
 * 1. 失败回写不得抹掉驱动已落库的检查点；
 * 2. 结算成功即落账单/实例标识（付款失败也能凭标识幂等回查）；
 * 3. 充值重试不得重新加购/结算，token 全程稳定。
 */
class ZjmfProvisionRetryCheckpointTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_PROVIDER_KEY = 'fake_zjmf_checkpoint';

    private int $pluginId = 0;

    /** @var array<int, array{uri: string, payload: array<string, mixed>}> */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['idc.hosting_panel_api.jwt_cache_store' => 'array']);

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
                'name' => 'ZJMF 重试检查点测试插件',
                'entry_class' => 'Tests\\FakeFixVerify',
                'status' => 1,
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_retry_after_balance_shortage_never_provisions_a_second_host(): void
    {
        [$order, $supplier, $service] = $this->makeBoundContext();

        // 第一次：结算成功，余额不足失败
        try {
            $this->submit($this->makeZjmf('400'), $order)->processPaidOrder($order);
            $this->fail('余额不足必须失败');
        } catch (BusinessException) {
        }

        $afterFailure = (array) ($service->fresh()->provision_data ?? []);
        $firstToken = (string) ($afterFailure['downstream_token'] ?? '');

        $this->assertSame(32, strlen($firstToken), '失败后 token 必须保留在 provision_data');
        $this->assertSame(77, (int) ($afterFailure['upstream_invoice_id'] ?? 0), '失败后账单标识必须保留');
        $this->assertSame(601, (int) ($afterFailure['upstream_host_id'] ?? 0), '失败后实例标识必须保留');

        // 充值后重试：检查点完整，直接走幂等回查确认既有实例并补付账单
        $this->calls = [];
        $result = $this->submit($this->makeZjmf('unpaid'), $order)->retryFailedProvision($order->fresh());

        $uris = array_column($this->calls, 'uri');

        $this->assertNotContains('/cart/add_to_shop', $uris, '不得重新加购');
        $this->assertNotContains('/cart/settle', $uris, '不得重新结算，否则会开出第二台同名主机');
        $this->assertSame(601, (int) data_get($result->provision_data, 'upstream_host_id'));
        $this->assertSame($firstToken, (string) data_get($result->provision_data, 'downstream_token'), '重试后 token 不得被改写');
        $this->assertSame(ServiceStatus::ACTIVE, (int) $result->status);
    }

    public function test_paid_upstream_invoice_skips_fund_during_idempotent_recheck(): void
    {
        // 账单已在上游付清：幂等回查必须只确认、不再调 /fund。
        // 上游对已付账单回 400「Bill paid」，若状态读错层级会把已付当未付、
        // 每次重试都在这里炸掉——充值后重试永远无法完成。
        [$order, , $service] = $this->makeBoundContext();

        $service->forceFill([
            'provision_data' => [
                'downstream_token' => str_repeat('e', 32),
                'upstream_invoice_id' => 77,
                'upstream_host_id' => 601,
                'upstream_host_ids' => [601],
                'requested_host' => 'fix-host.example.test',
            ],
        ])->save();

        $this->calls = [];
        $result = $this->submit($this->makeZjmf('paid'), $order)->retryFailedProvision($order->fresh());

        $uris = array_column($this->calls, 'uri');

        $this->assertNotContains('/v1/invoices/77/fund', $uris, '账单已付时不得再调补付接口');
        $this->assertNotContains('/cart/add_to_shop', $uris);
        $this->assertNotContains('/cart/settle', $uris);
        $this->assertSame(601, (int) data_get($result->provision_data, 'upstream_host_id'));
    }

    private function submit(ZjmfProvisionService $zjmf, Order $order): ProvisionService
    {
        $driver = new class($zjmf) implements ProvidesProvisioning, UpstreamDriver
        {
            public function __construct(private readonly ZjmfProvisionService $zjmf) {}

            public function key(): string
            {
                return 'fake_zjmf_checkpoint';
            }

            public function label(): string
            {
                return 'ZJMF 重试检查点测试驱动';
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
                return $this->zjmf->provisionOrder($order, $supplier, $service);
            }

            public function getProductProvisionConfig(Supplier $supplier, int $productId): array
            {
                return ['status' => 200, 'data' => []];
            }
        };

        return new ProvisionService(
            new ProviderResolver(new ProviderRegistry([$driver])),
            app(SettingService::class),
        );
    }

    private function makeZjmf(string $creditMode): ZjmfProvisionService
    {
        $hosting = Mockery::mock(HostingPanelApiTransport::class);
        $hosting->shouldReceive('request')->andReturnUsing(function (Supplier $supplier, string $method, string $uri) {
            if ($uri === '/zjmf_api_login') {
                return ['jwt' => 'unit-test-jwt', 'status' => 200, 'msg' => 'ok'];
            }

            throw new \RuntimeException('意外的 request：'.$uri);
        });
        $hosting->shouldReceive('requestWithMeta')->andReturnUsing(
            function (Supplier $supplier, string $method, string $uri, array|string $payload = []) use ($creditMode) {
                $this->calls[] = ['uri' => $uri, 'payload' => is_array($payload) ? $payload : []];

                if ($uri === '/zjmf_api_login') {
                    return [
                        'response' => ['jwt' => 'unit-test-jwt', 'status' => 200, 'msg' => 'ok'],
                        'headers' => [],
                        'http_code' => 200,
                        'content_type' => 'application/json',
                    ];
                }

                $response = match (true) {
                    $uri === '/cart/clear' && $creditMode === 'unpaid' => ['status' => 200, 'msg' => 'SUCCESS MESSAGE', 'invoiceid' => 77, 'hostid' => 601],
                    // 已付账单场景：上游清车按 token 命中主机且账单已付 → 400「已开通」门。
                    // 走的是 adoptExistingUpstreamHost 收编路径，同样不能重复下单。
                    $uri === '/cart/clear' && $creditMode === 'paid' => [
                        'status' => 400,
                        'msg' => '该订单已开通,请勿重新开通',
                        'hostid' => 601,
                        'domainstatus' => 'Active',
                    ],
                    $uri === '/cart/clear' => ['status' => 200, 'msg' => 'SUCCESS MESSAGE'],
                    $uri === '/cart/get_product_config' => ['status' => 200, 'msg' => 'ok', 'data' => []],
                    $uri === '/cart/add_to_shop' => ['status' => 200, 'msg' => 'ADD SUCCESS', 'i' => 0],
                    $uri === '/cart/settle' => ['status' => 200, 'msg' => 'ok', 'data' => ['invoiceid' => 77, 'hostid' => [601]]],
                    $uri === '/apply_credit' && $creditMode === '400' => ['status' => 400, 'msg' => '余额不足：账单需35.00，现余额0.00,上游账单#77完成支付后即可开通'],
                    $uri === '/apply_credit' => ['status' => 1001, 'msg' => '支付完成', 'data' => ['hostid' => [601]]],
                    // 与上游真实响应同形：账单状态在 data.invoices.status，
                    // 顶层 data.status 是业务码。mock 写错层级会让字段路径缺陷测不出来。
                    $uri === '/v1/invoices/77' => [
                        'status' => 200,
                        'msg' => 'ok',
                        'data' => ['invoices' => ['status' => $creditMode === 'paid' ? 'Paid' : 'Unpaid']],
                    ],
                    $uri === '/v1/invoices/77/fund' => ['status' => 1001, 'msg' => '支付完成', 'data' => ['hostid' => [601]]],
                    $uri === '/host/header' => [
                        'status' => 200,
                        'msg' => 'ok',
                        'data' => ['host_data' => [
                            'id' => 601,
                            'domain' => 'fix-host.example.test',
                            'productid' => 11,
                            'domainstatus' => 'Active',
                        ]],
                    ],
                    default => throw new \RuntimeException('意外的 requestWithMeta：'.$uri),
                };

                return [
                    'response' => $response,
                    'headers' => [],
                    'http_code' => 200,
                    'content_type' => 'application/json',
                ];
            }
        );

        return new ZjmfProvisionService(new ZjmfFinanceTransport($hosting, new ZjmfAuthManager($hosting)));
    }

    /**
     * @return array{0: Order, 1: Supplier, 2: Service}
     */
    private function makeBoundContext(): array
    {
        $user = User::factory()->create(['is_verified' => 1]);

        $supplier = Supplier::query()->create([
            'name' => 'ZJMF 检查点测试供应商-'.uniqid(),
            'code' => 'SUP-CKPT-'.uniqid(),
            'status' => 1,
        ]);

        $supplierBindingId = (int) DB::table('supplier_plugin_bindings')->insertGetId([
            'supplier_id' => $supplier->id,
            'plugin_id' => $this->pluginId,
            'provider_key' => self::FAKE_PROVIDER_KEY,
            'environment' => 'production',
            'status' => 1,
            'base_url' => 'https://fix-upstream.example.test',
            'account_name' => 'fix-account',
        ]);

        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'name' => 'ZJMF 检查点测试商品',
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
            'config_snapshot' => ['hostname' => 'fix-host.example.test', 'currencyid' => 1],
        ]);

        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'name' => 'ZJMF 检查点测试服务',
            'domain' => 'fix-host.example.test',
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::PENDING,
            'provision_data' => [],
        ]);

        $order->forceFill(['service_id' => $service->id])->save();

        return [$order->fresh(), $supplier, $service];
    }
}
