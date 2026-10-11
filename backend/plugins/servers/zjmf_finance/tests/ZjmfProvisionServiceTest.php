<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfAuthManager;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfFinanceTransport;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfProvisionService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/ZjmfFinanceTransport.php';
require_once __DIR__.'/../lib/ZjmfProvisionService.php';
require_once __DIR__.'/../lib/ZjmfAuthManager.php';

class ZjmfProvisionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function service(): ZjmfProvisionService
    {
        return (new ReflectionClass(ZjmfProvisionService::class))->newInstanceWithoutConstructor();
    }

    private function invoke(ZjmfProvisionService $service, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod(ZjmfProvisionService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($service, ...$args);
    }

    public function test_extract_host_ids_filters_invalid_values(): void
    {
        $service = $this->service();
        $method = new ReflectionMethod(ZjmfProvisionService::class, 'extractHostIds');
        $method->setAccessible(true);

        $response = [
            'data' => [
                'hostids' => ['101', 0, -3, 'abc', 102],
            ],
        ];

        /** @var array<int, string> $hostIds */
        $hostIds = $method->invoke($service, $response, $response['data']);

        $this->assertSame([101, 102], array_map('intval', $hostIds));
    }

    public function test_extract_invoice_id_prefers_payload_then_response(): void
    {
        $service = $this->service();
        $method = new ReflectionMethod(ZjmfProvisionService::class, 'extractInvoiceId');
        $method->setAccessible(true);

        $this->assertSame(88, $method->invoke($service, [], ['invoiceid' => 88]));
        $this->assertSame(88, $method->invoke($service, ['invoice_id' => 88], []));
        $this->assertSame(0, $method->invoke($service, [], []));
    }

    public function test_extract_cart_position_supports_zero_fallback(): void
    {
        $service = $this->service();
        $method = new ReflectionMethod(ZjmfProvisionService::class, 'extractCartPosition');
        $method->setAccessible(true);

        $this->assertSame(2, $method->invoke($service, ['data' => 2], []));
        $this->assertSame(0, $method->invoke($service, [], []));
    }

    public function test_build_config_option_map_skips_empty_values(): void
    {
        $service = $this->service();
        $method = new ReflectionMethod(ZjmfProvisionService::class, 'buildConfigOptionMap');
        $method->setAccessible(true);

        // Product 模型仅作数据容器使用，不触数据库。
        $product = new Product;
        $product->config_options = [
            [
                'id' => 11,
                'option_type' => 5,
                'option_name' => 'os|操作系统',
                'field' => 'os',
                'sub' => [
                    ['id' => 111, 'option_name' => 'centos|CentOS 7', 'option_name_first' => 'centos'],
                ],
            ],
            [
                'id' => 12,
                'option_type' => 7,
                'option_name' => 'cpu|CPU 核数',
                'field' => 'cpu',
                'sub' => [
                    ['id' => 121, 'option_name_first' => '2 核'],
                ],
            ],
        ];

        $snapshot = [
            'os' => 'centos',
            'cpu' => '',
        ];

        /** @var array<int, int> $result */
        $result = $method->invoke($service, $product, $snapshot);

        // cpu 空值被跳过，os 命中子项 111。
        $this->assertSame([11 => 111], $result);
    }

    public function test_resolve_option_id_falls_back_to_sub_config_id(): void
    {
        $service = $this->service();
        $method = new ReflectionMethod(ZjmfProvisionService::class, 'resolveOptionId');
        $method->setAccessible(true);

        $this->assertSame(0, $method->invoke($service, ['sub' => []]));
        $this->assertSame(7, $method->invoke($service, ['sub' => [['config_id' => 7]]]));
        $this->assertSame(3, $method->invoke($service, ['id' => 3]));
    }

    public function test_stock_shortage_message_detection(): void
    {
        // 上游售罄文案识别回归（订单 3202 事故）：魔方财务加购拒绝的原始 msg
        // 为「产品XXX库存不足」，命中即抛 UpstreamStockShortageException 供
        // 开通编排层校准本地库存；其余失败保持普通 BusinessException。
        // 「商品不存在」是上游对 hidden=1 已下架商品的加购拒绝文案；
        // 「产品不存在」是上游商品被删除时 get_product_config 的拒绝（CART_PRO_CONF_NOTFOUND）；
        // 「超出库存」是升级换品目标商品库存 0 的拒绝（PRODUCT_OUT_OF_STOCK）。
        // 词表真源在 UpstreamStockShortageException::matches，此处回归开通出口的代理。
        $service = $this->service();

        $this->assertTrue($this->invoke($service, 'isStockShortageMessage', ['产品美国轻量云 2H2G库存不足']));
        $this->assertTrue($this->invoke($service, 'isStockShortageMessage', ['商品已售罄']));
        $this->assertTrue($this->invoke($service, 'isStockShortageMessage', ['该套餐已售完']));
        $this->assertTrue($this->invoke($service, 'isStockShortageMessage', ['商品不存在']));
        $this->assertTrue($this->invoke($service, 'isStockShortageMessage', ['产品不存在']));
        $this->assertTrue($this->invoke($service, 'isStockShortageMessage', ['超出库存']));
        $this->assertTrue($this->invoke($service, 'isStockShortageMessage', ['Out of Stock']));
        $this->assertFalse($this->invoke($service, 'isStockShortageMessage', ['参数错误']));
        $this->assertFalse($this->invoke($service, 'isStockShortageMessage', ['鉴权失败']));
        $this->assertFalse($this->invoke($service, 'isStockShortageMessage', ['']));
    }

    /**
     * 清车幂等门分类回归：携带 downstream_* 时上游 /cart/clear 对 is_api 客户
     * 不是单纯清车——按 token 命中既有主机时，账单已支付回 400「已开通」+hostid（收编），
     * 未支付回 200+invoiceid+hostid（补付）；未命中或未携带注册参数时为普通清车。
     */
    public function test_clear_cart_classifies_downstream_gate_responses(): void
    {
        $downstream = [
            'downstream_url' => 'http://downstream.test',
            'downstream_token' => str_repeat('a', 32),
            'downstream_id' => 9,
        ];

        // 普通 200 清车 → 不触发门
        $gate = $this->clearCartWithResponse(['status' => 200, 'msg' => 'SUCCESS MESSAGE'], $downstream);
        $this->assertSame(['gate' => 'none'], $gate);

        // 未携带 downstream_*（如 finally 兜底清车）→ 不触发门
        $gate = $this->clearCartWithResponse(['status' => 200, 'msg' => 'SUCCESS MESSAGE'], []);
        $this->assertSame(['gate' => 'none'], $gate);

        // 200 + invoiceid + hostid → 未支付门：跳过加购/结算直接补付
        $gate = $this->clearCartWithResponse([
            'status' => 200,
            'msg' => 'SUCCESS MESSAGE',
            'invoiceid' => 77,
            'hostid' => 501,
        ], $downstream);
        $this->assertSame(['gate' => 'unpaid', 'invoice_id' => 77, 'host_id' => 501], $gate);

        // 400「已开通」+ hostid → 已支付门：收编既有实例
        $gate = $this->clearCartWithResponse([
            'status' => 400,
            'msg' => '该订单已开通,请勿重新开通',
            'hostid' => 501,
            'domainstatus' => 'Active',
        ], $downstream);
        $this->assertSame(['gate' => 'paid', 'host_id' => 501], $gate);
    }

    public function test_paid_gate_without_host_id_is_rejected(): void
    {
        // 上游文案命中但未回 hostid：无法收编，必须显式失败转人工，
        // 绝不能按普通失败处理后再走一次加购结算造成上游二次开通。
        $service = $this->service();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('请人工核实');

        $this->invoke($service, 'adoptExistingUpstreamHost', [null, new Supplier, new Product, 0, 'unit-test-jwt']);
    }

    /**
     * @param array<string, mixed> $response 上游 /cart/clear 响应体
     * @param array<string, int|string> $downstream downstream_* 注册参数
     */
    private function clearCartWithResponse(array $response, array $downstream): array
    {
        $hosting = $this->hostingTransportMock($response);

        $service = new ZjmfProvisionService(
            new ZjmfFinanceTransport($hosting, new ZjmfAuthManager($hosting)),
        );

        $cookieJar = [];
        $args = [new Supplier, 'unit-test-jwt', &$cookieJar, $downstream];

        /** @var array{gate: string} $gate */
        $gate = $this->invokeArgs($service, 'clearCart', $args);

        return $gate;
    }

    private function invokeArgs(ZjmfProvisionService $service, string $method, array &$args): mixed
    {
        $reflection = new ReflectionMethod(ZjmfProvisionService::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($service, $args);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function hostingTransportMock(array $response): MockInterface
    {
        $hosting = Mockery::mock(HostingPanelApiTransport::class);
        $hosting->shouldReceive('requestWithMeta')->andReturnUsing(fn (): array => [
            'response' => $response,
            'headers' => [],
            'http_code' => 200,
            'content_type' => 'application/json',
        ]);

        return $hosting;
    }
}
