<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\ServiceStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfAuthManager;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfProvisionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，Feature 测试中需手动引入
require_once __DIR__.'/../../plugins/servers/zjmf_finance/lib/ZjmfFinanceTransport.php';
require_once __DIR__.'/../../plugins/servers/zjmf_finance/lib/ZjmfAuthManager.php';
require_once __DIR__.'/../../plugins/servers/zjmf_finance/lib/ZjmfProvisionService.php';

/**
 * ZJMF 下游购物车链路幂等门回归（对齐魔方财务同系统协议，接口契约见
 * docs/参考资料/集成/魔方财务同系统对接API.md 第五/六节）：
 * - 购物车链路（clear/settle/apply_credit）携带 downstream_url/token/id 注册推送目标，
 *   上游据此把 stream_info 写到主机并激活两级幂等门；
 * - /cart/clear 幂等门：命中既有主机且账单已支付 → 收编（400+hostid，绝不重走加购结算）；
 *   命中且未支付 → 跳过加购/结算直接补付既有账单（防止重开一单二次扣款）；
 * - /cart/settle 同 token 防重门返回 1001+既有实例时收编，不再因缺账单 ID 报错；
 * - token 按服务持久化、跨重试稳定（上游按 token 匹配既有主机，换 token 即放弃幂等）；
 * - app.url 缺失时退化为历史行为：不注册、各门不激活。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ZjmfProvisionDownstreamGateTest extends TestCase
{
    use DatabaseTransactions;

    private const FAKE_PROVIDER_KEY = 'fake_zjmf_gate';

    private int $pluginId = 0;

    /** @var array<int, array{uri: string, payload: array<string, mixed>, query: array<string, mixed>}> */
    private array $cartCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        // JWT 缓存与防抖标记切 array store，避免落真实 redis 造成用例间串扰
        config(['idc.hosting_panel_api.jwt_cache_store' => 'array']);

        // 注册 fake 插件行：绑定链解析依赖 integration_plugins；
        // 插入发生在测试事务内，由 DatabaseTransactions 统一回滚
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
                'name' => '测试 ZJMF 幂等门插件',
                'entry_class' => 'Tests\\FakeZjmfGate',
                'status' => 1,
            ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_paid_gate_adopts_existing_host_without_reprovision(): void
    {
        // 崩溃恢复场景：上次开通已付款成功但本站检查点丢失。清车幂等门
        // 按 downstream_token 命中已支付主机（400+hostid），必须收编而非重开。
        $service = $this->makeProvisionService([
            '/cart/clear' => ['status' => 400, 'msg' => '该订单已开通,请勿重新开通', 'hostid' => 501, 'domainstatus' => 'Active'],
            '/host/header' => $this->hostDetailResponse(501),
        ]);
        [$order, $supplier, $localService] = $this->makeBoundContext();

        $result = $service->provisionOrder($order, $supplier, $localService);

        $this->assertSame(501, $result['upstream_host_id']);

        $uris = array_column($this->cartCalls, 'uri');
        $this->assertContains('/cart/clear', $uris);
        $this->assertNotContains('/cart/add_to_shop', $uris, '已开通已付的主机不得重走加购');
        $this->assertNotContains('/cart/settle', $uris, '已开通已付的主机不得重走结算');
        $this->assertNotContains('/apply_credit', $uris);

        $this->assertSame(501, (int) ($localService->fresh()->provision_data['upstream_host_id'] ?? 0));
    }

    public function test_unpaid_gate_pays_existing_invoice_then_adopts(): void
    {
        // 崩溃恢复场景：上次开通在结算后、支付前中断。清车幂等门命中
        // 未支付旧账单（200+invoiceid+hostid），必须补付该账单而非重开一单。
        $service = $this->makeProvisionService([
            '/cart/clear' => ['status' => 200, 'msg' => 'SUCCESS MESSAGE', 'invoiceid' => 77, 'hostid' => 501],
            '/apply_credit' => ['status' => 1001, 'msg' => '支付完成', 'data' => ['hostid' => [501]]],
            '/host/header' => $this->hostDetailResponse(501),
        ]);
        [$order, $supplier, $localService] = $this->makeBoundContext();

        $result = $service->provisionOrder($order, $supplier, $localService);

        $this->assertSame(77, $result['upstream_invoice_id']);
        $this->assertSame(501, $result['upstream_host_id']);

        $uris = array_column($this->cartCalls, 'uri');
        $this->assertNotContains('/cart/add_to_shop', $uris, '旧账单未支付时不得重走加购');
        $this->assertNotContains('/cart/settle', $uris, '旧账单未支付时不得重走结算');

        $creditCall = $this->callByUri('/apply_credit');
        $this->assertSame(77, (int) $creditCall['payload']['invoiceid']);
        $this->assertSame(1, (int) $creditCall['payload']['use_credit']);
        $this->assertSame(1, (int) $creditCall['payload']['enough']);
        $this->assertSame('http://api.example.test', $creditCall['payload']['downstream_url']);
        $this->assertSame(32, strlen((string) $creditCall['payload']['downstream_token']));

        $fresh = $localService->fresh();
        $this->assertSame(77, (int) ($fresh->provision_data['upstream_invoice_id'] ?? 0));
        $this->assertSame($creditCall['payload']['downstream_token'], $fresh->provision_data['downstream_token'] ?? null);
    }

    public function test_normal_flow_registers_downstream_params_on_settle_and_credit(): void
    {
        $service = $this->makeProvisionService([
            '/cart/clear' => ['status' => 200, 'msg' => 'SUCCESS MESSAGE'],
            '/cart/get_product_config' => ['status' => 200, 'msg' => 'ok', 'data' => []],
            '/cart/add_to_shop' => ['status' => 200, 'msg' => 'ADD SUCCESS'],
            '/cart/settle' => ['status' => 200, 'msg' => 'ok', 'data' => ['invoiceid' => 88]],
            '/apply_credit' => ['status' => 1001, 'msg' => '支付完成', 'data' => ['hostid' => [601]]],
            '/host/header' => $this->hostDetailResponse(601),
        ]);
        [$order, $supplier, $localService] = $this->makeBoundContext();

        $result = $service->provisionOrder($order, $supplier, $localService);

        $this->assertSame(601, $result['upstream_host_id']);
        $this->assertSame(88, $result['upstream_invoice_id']);

        $token = (string) ($localService->fresh()->provision_data['downstream_token'] ?? '');
        $this->assertSame(32, strlen($token), 'downstream token 必须落库且为 32 位');

        $settleCall = $this->callByUri('/cart/settle');
        $this->assertSame([0], $settleCall['payload']['pos']);
        $this->assertSame(1, (int) $settleCall['payload']['checkout']);
        $this->assertSame('http://api.example.test', $settleCall['payload']['downstream_url']);
        $this->assertSame($token, $settleCall['payload']['downstream_token']);
        $this->assertSame((int) $localService->id, (int) $settleCall['payload']['downstream_id']);

        $creditCall = $this->callByUri('/apply_credit');
        $this->assertSame(88, (int) $creditCall['payload']['invoiceid']);
        $this->assertSame($token, $creditCall['payload']['downstream_token']);
    }

    public function test_settle_duplicate_gate_1001_adopts_without_invoice(): void
    {
        // 上游 /cart/settle 同 token 防重门：重入时返回 1001+既有 hostid（无账单 ID）。
        // 实例已存在、无账单可付——必须收编成功，不得因缺账单 ID 抛错。
        $service = $this->makeProvisionService([
            '/cart/clear' => ['status' => 200, 'msg' => 'SUCCESS MESSAGE'],
            '/cart/get_product_config' => ['status' => 200, 'msg' => 'ok', 'data' => []],
            '/cart/add_to_shop' => ['status' => 200, 'msg' => 'ADD SUCCESS'],
            '/cart/settle' => ['status' => 1001, 'msg' => 'BUY_SUCCESS', 'data' => ['hostid' => [601]]],
            '/host/header' => $this->hostDetailResponse(601),
        ]);
        [$order, $supplier, $localService] = $this->makeBoundContext();

        $result = $service->provisionOrder($order, $supplier, $localService);

        $this->assertSame(601, $result['upstream_host_id']);

        $uris = array_column($this->cartCalls, 'uri');
        $this->assertNotContains('/apply_credit', $uris, '防重门命中时无账单可付，不得再调余额支付');
        $this->assertSame(601, (int) ($localService->fresh()->provision_data['upstream_host_id'] ?? 0));
    }

    public function test_downstream_token_is_persisted_and_stable_across_calls(): void
    {
        // token 跨重试稳定：上游按 token 匹配既有主机，重试换 token 等于放弃幂等门。
        $provision = $this->makeProvisionService([]);
        [$order, $supplier, $localService] = $this->makeBoundContext();

        $method = new ReflectionMethod(ZjmfProvisionService::class, 'resolveDownstreamContext');
        $method->setAccessible(true);

        $first = $method->invoke($provision, $localService);
        $second = $method->invoke($provision, $localService);

        $this->assertSame('http://api.example.test', $first['downstream_url']);
        $this->assertSame(32, strlen($first['downstream_token']));
        $this->assertSame((int) $localService->id, $first['downstream_id']);
        $this->assertSame($first, $second, '同一服务的 downstream 上下文必须完全一致');
        $this->assertSame($first['downstream_token'], $localService->fresh()->provision_data['downstream_token'] ?? null);
    }

    public function test_missing_app_url_falls_back_to_legacy_behavior(): void
    {
        // app.url 缺失时退化为历史行为：不注册 downstream_*，上游各幂等门不激活。
        config(['app.url' => '']);

        $service = $this->makeProvisionService([
            '/cart/clear' => ['status' => 200, 'msg' => 'SUCCESS MESSAGE'],
            '/cart/get_product_config' => ['status' => 200, 'msg' => 'ok', 'data' => []],
            '/cart/add_to_shop' => ['status' => 200, 'msg' => 'ADD SUCCESS'],
            '/cart/settle' => ['status' => 200, 'msg' => 'ok', 'data' => ['invoiceid' => 88]],
            '/apply_credit' => ['status' => 1001, 'msg' => '支付完成', 'data' => ['hostid' => [601]]],
            '/host/header' => $this->hostDetailResponse(601),
        ]);
        [$order, $supplier, $localService] = $this->makeBoundContext();

        $result = $service->provisionOrder($order, $supplier, $localService);

        $this->assertSame(601, $result['upstream_host_id']);

        $settleCall = $this->callByUri('/cart/settle');
        $this->assertArrayNotHasKey('downstream_url', $settleCall['payload']);
        $this->assertArrayNotHasKey('downstream_token', $settleCall['payload']);
        $this->assertArrayNotHasKey('downstream_token', $localService->fresh()->provision_data ?? []);
    }

    /**
     * 构造脚本化的开通服务：底层传输按 URI 返回预置响应并记录购物车链调用。
     *
     * @param  array<string, array<string, mixed>>  $cartResponses  uri → 响应体（requestWithMeta 链）
     */
    private function makeProvisionService(array $cartResponses): ZjmfProvisionService
    {
        $hosting = $this->mockHostingTransport($cartResponses);

        return new ZjmfProvisionService(
            new ZjmfFinanceTransport($hosting, new ZjmfAuthManager($hosting)),
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $cartResponses
     */
    private function mockHostingTransport(array $cartResponses): MockInterface
    {
        $this->cartCalls = [];
        $hosting = Mockery::mock(HostingPanelApiTransport::class);
        $hosting->shouldReceive('request')->andReturnUsing(function (Supplier $supplier, string $method, string $uri) {
            if ($uri === '/zjmf_api_login') {
                return ['jwt' => 'unit-test-jwt', 'status' => 200, 'msg' => 'ok'];
            }

            throw new RuntimeException('意外的上游请求：'.$uri);
        });
        $hosting->shouldReceive('requestWithMeta')->andReturnUsing(
            function (Supplier $supplier, string $method, string $uri, array|string $payload = [], ?string $jwt = null, array $headers = [], array $query = []) use ($cartResponses) {
                $this->cartCalls[] = [
                    'uri' => $uri,
                    'payload' => is_array($payload) ? $payload : [],
                    'query' => $query,
                ];

                if (! array_key_exists($uri, $cartResponses)) {
                    throw new RuntimeException('意外的上游请求：'.$uri);
                }

                return [
                    'response' => $cartResponses[$uri],
                    'headers' => [],
                    'http_code' => 200,
                    'content_type' => 'application/json',
                ];
            }
        );

        return $hosting;
    }

    /**
     * @return array{uri: string, payload: array<string, mixed>, query: array<string, mixed>}
     */
    private function callByUri(string $uri): array
    {
        foreach ($this->cartCalls as $call) {
            if ($call['uri'] === $uri) {
                return $call;
            }
        }

        $this->fail('未发生预期的上游请求：'.$uri);
    }

    /**
     * 造「供应商 → 商品绑定 → 已付订单 → 服务」完整开通上下文。
     *
     * @return array{0: Order, 1: Supplier, 2: Service}
     */
    private function makeBoundContext(): array
    {
        $user = User::factory()->create(['is_verified' => 1]);
        $supplier = Supplier::query()->create([
            'name' => 'ZJMF-幂等门-'.uniqid(),
            'code' => 'SUP-ZJMF-GATE-'.uniqid(),
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
            'name' => '幂等门测试商品-'.uniqid(),
            'status' => 1,
            'stock' => -1,
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
            'order_no' => 'GATE'.date('YmdHis').mt_rand(1000, 9999),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => OrderType::NEW,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'paid_amount' => 35.00,
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
            'config_snapshot' => [
                'hostname' => 'gate-host.example.test',
                'currencyid' => 1,
            ],
        ]);
        $localService = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_id' => $order->id,
            'name' => '幂等门测试服务-'.uniqid(),
            'domain' => 'gate-host.example.test',
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::PENDING,
            'provision_data' => [],
        ]);

        return [$order, $supplier, $localService];
    }

    private function hostDetailResponse(int $hostId): array
    {
        return [
            'status' => 200,
            'msg' => 'ok',
            'data' => [
                'host_data' => [
                    'id' => $hostId,
                    'domain' => 'gate-host.example.test',
                    'productid' => 11,
                    'domainstatus' => 'Active',
                ],
            ],
        ];
    }
}
