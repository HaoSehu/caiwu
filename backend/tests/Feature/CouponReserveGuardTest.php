<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\UserCouponStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\CouponService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 优惠券占用-释放双花守护（恢复 9d998ea3 删除的 CouponDoubleSpendGuardTest 并补强）。
 *
 * P0 修复回归：reserve 除已支付账单兜底外，还必须拦截同券待支付（UNPAID）占用，
 * 且释放回落 OWNED 时不得清掉其他待支付账单刚写入的预留。
 * 退款口径（2026-10-11）：全额退款（REFUNDED）视为已消耗——券置 USED 不回落，
 * 占用拦截与列表排除同口径；取消（CANCELLED）仍回落可复用。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class CouponReserveGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reserve_rejects_coupon_that_already_has_paid_invoice(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();

        // 该券已用于一笔已支付账单，但异步核销同步未完成（券仍 OWNED 且预留已过期）
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::PAID);
        $userCoupon->forceFill(['reserved_until' => now()->subMinutes(11)])->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('优惠券已使用');

        app(CouponService::class)->reserveOwnedCouponForInvoice(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            20.0,
            'new'
        );
    }

    public function test_reserve_rejects_coupon_with_pending_unpaid_invoice(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();

        // 同券已有一张待支付账单（预留已过期）：再次占用会形成并存可支付账单，双花窗口
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::UNPAID);
        $userCoupon->forceFill(['reserved_until' => now()->subMinutes(11)])->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('待支付');

        app(CouponService::class)->reserveOwnedCouponForInvoice(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            20.0,
            'new'
        );
    }

    public function test_reserve_allows_coupon_without_blocking_invoices(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();

        $payload = app(CouponService::class)->reserveOwnedCouponForInvoice(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            20.0,
            'new'
        );

        $this->assertIsArray($payload);
        $this->assertSame('5.00', (string) ($payload['discount_amount'] ?? ''));
        $this->assertNotNull($userCoupon->refresh()->reserved_until);
    }

    public function test_release_resets_owned_and_clears_reservation_for_single_invoice(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();

        $invoice = $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::UNPAID);
        $userCoupon->forceFill(['reserved_until' => now()->addMinutes(10)])->save();

        app(CouponService::class)->releaseInvoiceCoupon($invoice);

        $userCoupon->refresh();
        $this->assertSame(UserCouponStatus::OWNED, (int) $userCoupon->status);
        $this->assertNull($userCoupon->reserved_until);
    }

    public function test_release_keeps_reservation_when_other_unpaid_invoice_exists(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();

        // 模拟并发残留：同券两张待支付账单，另一张（B）刚写入活跃预留
        $invoiceA = $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::UNPAID);
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::UNPAID);
        $reservedUntil = now()->addMinutes(10);
        $userCoupon->forceFill(['reserved_until' => $reservedUntil])->save();

        app(CouponService::class)->releaseInvoiceCoupon($invoiceA);

        $userCoupon->refresh();
        // 释放 A 不得清掉 B 的活跃预留，否则第三张账单可再次占用同一张券
        $this->assertSame(UserCouponStatus::OWNED, (int) $userCoupon->status);
        $this->assertNotNull($userCoupon->reserved_until);
        $this->assertTrue($userCoupon->reserved_until->gt(now()));
    }

    /**
     * @return array{0: User, 1: UserCoupon, 2: Product}
     */
    private function fixture(): array
    {
        $user = User::factory()->create();
        $coupon = app(CouponService::class)->createCoupon([
            'name' => '守护券'.uniqid(),
            'code' => 'GUARD'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'min_amount' => 0,
            'status' => 1,
        ], ['operator' => 'tester']);
        $userCoupon = UserCoupon::query()
            ->where('user_id', (int) $user->id)
            ->where('coupon_id', (int) $coupon['id'])
            ->firstOrFail();
        $product = Product::query()->create([
            'name' => '守护商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '20.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);

        return [$user, $userCoupon, $product];
    }

    /**
     * 全额退款落地（PAID -> REFUNDED）：券视为已消耗置 USED，不回落 OWNED。
     * 防「购-付-退」循环薅券；取消（CANCELLED）路径仍回落可复用
     * （见 TradeLifecycleEquivalenceTest::test_order_cancel_cascades_invoice_and_coupon）。
     */
    public function test_refunded_invoice_marks_coupon_used_instead_of_reverting(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();

        $invoice = $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::REFUNDED);

        app(CouponService::class)->syncInvoiceCouponUsage($invoice);

        $userCoupon->refresh();
        $this->assertSame(UserCouponStatus::USED, (int) $userCoupon->status);
        $this->assertNotNull($userCoupon->used_at);
        $this->assertNull($userCoupon->reserved_until);
    }

    /**
     * 退款已落地但异步核销同步尚未完成（券仍 OWNED、预留已过期）：
     * 占用拦截必须把已退款账单与已支付账单同口径拦截（USED_INVOICE_STATUSES 含 REFUNDED）。
     */
    public function test_reserve_rejects_coupon_that_already_has_refunded_invoice(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();

        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::REFUNDED);
        $userCoupon->forceFill(['reserved_until' => now()->subMinutes(11)])->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('优惠券已使用');

        app(CouponService::class)->reserveOwnedCouponForInvoice(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            20.0,
            'new'
        );
    }

    private function createInvoice(User $user, UserCoupon $userCoupon, Product $product, int $status): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'coupon_id' => (int) $userCoupon->coupon_id,
            'user_coupon_id' => (int) $userCoupon->id,
            'type' => 'new',
            'amount' => '15.00',
            'paid_amount' => in_array($status, [InvoiceStatus::PAID, InvoiceStatus::REFUNDED], true) ? '15.00' : '0.00',
            'billing_cycle' => 'monthly',
            'status' => $status,
            'paid_at' => in_array($status, [InvoiceStatus::PAID, InvoiceStatus::REFUNDED], true) ? now() : null,
            'due_date' => now()->addDay(),
        ]);
    }
}
