<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * Money 整数分 API（批 1）特征测试：锁定与既有 float 口径的逐字节等价。
 *
 * 边界集合：0.1+0.2 类精度、half-up 舍入边界（0.005/0.015/1.005/2.675）、
 * 大额 999,999.99（decimal(12,2) 量级）、负数、数字字符串与 null。
 */
final class MoneyCentsTest extends TestCase
{
    public function test_to_cents_is_byte_equivalent_to_existing_round_and_format(): void
    {
        $values = [
            '0.005', '0.015', '0.025', '1.005', '2.675', '12.345',
            '0.1', '0.3', '999999.99', '-0.05', '-1234.56', '0', '12.30',
        ];

        foreach ($values as $value) {
            $expected = (int) round(Money::round($value) * 100);
            $this->assertSame($expected, Money::toCents($value), "toCents({$value}) 应与既有 round 口径一致");

            // 分 → 元 → 格式化：与直连格式化逐字节一致（展示/导出侧输出不得变化）。
            $roundTrip = Money::fromCents(Money::toCents($value));
            $this->assertSame(
                Money::format($value),
                Money::format($roundTrip),
                "format 往返({$value}) 应逐字节一致"
            );
            $this->assertSame(Money::round($value), $roundTrip, "round 往返({$value}) 应数值相等");
        }
    }

    public function test_half_up_rounding_is_locked(): void
    {
        // 显式锁定 half-up（四舍五入），防止未来误切银行家舍入产生 1 分偏差。
        $this->assertSame(1, Money::toCents('0.005'));
        $this->assertSame(2, Money::toCents('0.015'));
        $this->assertSame(3, Money::toCents('0.025'));
        $this->assertSame(101, Money::toCents('1.005'));
        $this->assertSame(268, Money::toCents('2.675'));
        $this->assertSame(-1, Money::toCents('-0.005'));
    }

    public function test_cents_sum_eliminates_float_addition_artifact(): void
    {
        // 0.1 + 0.2 的 float 直接相加是 0.30000000000000004；以分为单位求和精确。
        $this->assertNotSame(0.3, 0.1 + 0.2);
        $this->assertSame(30, Money::toCents(0.1) + Money::toCents(0.2));

        $sum = Money::fromCents(Money::toCents(0.1) + Money::toCents(0.2));
        $this->assertSame(0.3, $sum);
        $this->assertSame(Money::format(0.3), Money::format($sum));
        $this->assertTrue(Money::equals($sum, 0.3));
    }

    public function test_compare_and_equals_use_cents(): void
    {
        $this->assertSame(0, Money::compare(0.3, 0.1 + 0.2));
        $this->assertSame(-1, Money::compare('1.00', '1.01'));
        $this->assertSame(1, Money::compare(2, 1.99));
        // 先按分位 half-up 舍入再比较：1.999 舍入到 2.00，与 2 相等
        $this->assertSame(0, Money::compare(2, 1.999));
        $this->assertTrue(Money::equals('10.00', 10));
        $this->assertTrue(Money::equals(-0.0, 0));
        $this->assertFalse(Money::equals(0.01, 0.02));
        $this->assertFalse(Money::equals(1, 1.01));
    }

    public function test_null_and_numeric_string_inputs_follow_existing_cast_semantics(): void
    {
        $this->assertSame(0, Money::toCents(null));
        $this->assertSame(0, Money::toCents(''));
        $this->assertSame(1230, Money::toCents('12.30'));
        $this->assertSame(-5, Money::toCents('-0.05'));
        $this->assertSame(0.0, Money::fromCents(0));
    }

    public function test_from_cents_round_trips_integer_cents(): void
    {
        foreach ([123456, -7, 1, 99999999] as $cents) {
            $this->assertSame($cents, Money::toCents(Money::fromCents($cents)));
        }

        $this->assertSame(-1234.56, Money::fromCents(-123456));
        $this->assertSame(999999.99, Money::fromCents(99999999));
    }
}
