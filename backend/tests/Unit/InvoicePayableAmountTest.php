<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Invoice;
use PHPUnit\Framework\TestCase;

/**
 * Invoice::payableAmount() 特征测试：锁定应付额的唯一实现口径。
 *
 * 应付额 = 折后应付 amount − 已付 paid_amount，不足零按零。
 * 历史上有 12 处手写副本分三种写法（钳零 / 不钳零 / 不 round），
 * 本测试锁定「钳零 + 两位小数」这一支，防止后续收敛回退成不钳零写法。
 */
final class InvoicePayableAmountTest extends TestCase
{
    public function test_payable_amount_is_amount_minus_paid_amount(): void
    {
        $invoice = new Invoice(['amount' => '100.00', 'paid_amount' => '30.00']);

        $this->assertSame(70.0, $invoice->payableAmount());
    }

    public function test_payable_amount_clamps_negative_to_zero(): void
    {
        // 溢付（已付大于应付）必须归零而不是返回负数：
        // 收银台据此判断「是否还需付款」，负数会被当成可退金额误用。
        $invoice = new Invoice(['amount' => '50.00', 'paid_amount' => '80.00']);

        $this->assertSame(0.0, $invoice->payableAmount());
    }

    public function test_payable_amount_treats_null_paid_amount_as_zero(): void
    {
        $invoice = new Invoice(['amount' => '12.34', 'paid_amount' => null]);

        $this->assertSame(12.34, $invoice->payableAmount());
    }

    public function test_payable_amount_is_rounded_to_two_decimals(): void
    {
        // 浮点尾差不得泄漏到调用方：中间运算必须收敛到两位小数。
        $invoice = new Invoice(['amount' => '0.30', 'paid_amount' => '0.10']);

        $this->assertSame(0.2, $invoice->payableAmount());
    }
}
