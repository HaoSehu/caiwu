<?php

declare(strict_types=1);

namespace Tests\Unit;

use Caiwu\Plugins\Servers\ZjmfFinance\Lib\ZjmfBillingRestoreService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/ZjmfBillingRestoreProfile.php';
require_once __DIR__.'/../lib/ZjmfBillingRestoreService.php';

class ZjmfBillingRestoreServiceTest extends TestCase
{
    private function service(): ZjmfBillingRestoreService
    {
        return (new ReflectionClass(ZjmfBillingRestoreService::class))->newInstanceWithoutConstructor();
    }

    private function method(string $name): ReflectionMethod
    {
        $method = new ReflectionMethod(ZjmfBillingRestoreService::class, $name);
        $method->setAccessible(true);

        return $method;
    }

    public function test_parse_insert_statement_handles_multiple_rows_and_escapes(): void
    {
        $service = $this->service();
        $statement = "INSERT INTO `shd_invoices` VALUES (1,'abc''def',NULL,'2026-01-01 00:00:00'),(2,'x\\\\y',100);";

        $rows = $this->method('parseInsertStatement')->invoke($service, $statement, 'shd_invoices');

        $this->assertCount(2, $rows);
        $this->assertSame(1, $rows[0][0]);
        // SQL 转义反斜杠在 stripcslashes 下还原为单一反斜杠。
        $this->assertSame('x\\y', $rows[1][1]);
    }

    public function test_decimal_compare_and_negate_avoid_float_drift(): void
    {
        $service = $this->service();

        $this->assertSame(0, $this->method('decimalCompare')->invoke($service, '0.1', '0.10'));
        $this->assertSame(-1, $this->method('decimalCompare')->invoke($service, '99.99', '100.00'));
        $this->assertSame('0.01', $this->method('decimalNegate')->invoke($service, '-0.01'));
        $this->assertSame('-99999999.99', $this->method('decimalNegate')->invoke($service, '99999999.99'));
    }

    public function test_to_cents_handles_large_and_negative_values(): void
    {
        $service = $this->service();

        // 大金额 + 两位小数直接收编为整数分，无 float 中间态。
        $this->assertSame(9999999999, $this->method('toCents')->invoke($service, '99999999.99'));
        $this->assertSame(-12345, $this->method('toCents')->invoke($service, '-123.45'));
        $this->assertSame(0, $this->method('toCents')->invoke($service, '0.00'));
    }

    public function test_from_cents_formats_two_digits(): void
    {
        $service = $this->service();

        $this->assertSame('123.05', $this->method('fromCents')->invoke($service, 12305));
        $this->assertSame('-0.01', $this->method('fromCents')->invoke($service, -1));
        $this->assertSame('0.00', $this->method('fromCents')->invoke($service, 0));
    }

    public function test_normalize_balance_amount_negates_consume(): void
    {
        $service = $this->service();

        $amount = $this->method('normalizeBalanceAmount')->invoke(
            $service,
            'credit applied 10.00',
            '10.00'
        );

        $this->assertSame('-10.00', $amount);
    }
}
