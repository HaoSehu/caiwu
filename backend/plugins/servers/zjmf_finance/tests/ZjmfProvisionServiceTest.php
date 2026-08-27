<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfProvisionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/ZjmfFinanceTransport.php';
require_once __DIR__.'/../lib/ZjmfProvisionService.php';

class ZjmfProvisionServiceTest extends TestCase
{
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
}
