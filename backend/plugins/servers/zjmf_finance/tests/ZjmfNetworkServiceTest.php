<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BusinessException;
use App\Exceptions\UpstreamStockShortageException;
use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfNetworkService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/ZjmfNetworkService.php';

class ZjmfNetworkServiceTest extends TestCase
{
    /**
     * ZjmfNetworkService 为 final 类且构造依赖 transport/consoleService，
     * 失败出口分类不依赖二者，经反射实例化后直接测私有 throwUpstreamFailure。
     */
    private function invokeThrowUpstreamFailure(array $response, string $action): \Throwable
    {
        $service = (new ReflectionClass(ZjmfNetworkService::class))->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(ZjmfNetworkService::class, 'throwUpstreamFailure');
        $method->setAccessible(true);

        try {
            $method->invoke($service, $response, $action);
        } catch (\Throwable $exception) {
            return $exception;
        }

        $this->fail('throwUpstreamFailure 未抛出异常');
    }

    public function test_upgrade_failure_with_stock_word_throws_classified_exception(): void
    {
        // 升级/控制台链路的上游失败出口（NetworkService）与开通同款分类：
        // 词表命中（如升级换品命中 PRODUCT_OUT_OF_STOCK 的「超出库存」）抛分类异常，
        // 供 ServiceUpgradeService 校准目标商品本地库存。
        $exception = $this->invokeThrowUpstreamFailure([
            'status' => 400,
            'msg' => '超出库存',
        ], '生成产品升降级账单');

        $this->assertInstanceOf(UpstreamStockShortageException::class, $exception);
        // BusinessException 第二参是业务 errorCode（getCode() 返回的是 httpStatus）
        $this->assertSame(42200, $exception->getErrorCode());
    }

    public function test_upgrade_failure_with_product_missing_throws_classified_exception(): void
    {
        $exception = $this->invokeThrowUpstreamFailure([
            'status' => 400,
            'msg' => '产品不存在',
        ], '生成产品升降级账单');

        $this->assertInstanceOf(UpstreamStockShortageException::class, $exception);
    }

    public function test_upgrade_failure_without_stock_word_keeps_plain_exception(): void
    {
        $exception = $this->invokeThrowUpstreamFailure([
            'status' => 400,
            'msg' => '参数错误',
        ], '生成产品升降级账单');

        $this->assertInstanceOf(BusinessException::class, $exception);
        $this->assertNotInstanceOf(UpstreamStockShortageException::class, $exception);
        $this->assertSame(42200, $exception->getErrorCode());
    }
}
