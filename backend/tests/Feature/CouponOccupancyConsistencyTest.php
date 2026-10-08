<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\CouponService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 券「已占用」口径一致性回归。
 *
 * 覆盖三类此前不一致的场景：
 * 1. 同券已有待支付账单时，结账可用券列表仍列出该券（可选但结账必败）；
 * 2. 预留中/已有待支付账单的券掉出「可用/已用完/已过期」三个分组（分组之和 < total），
 *    且展示层仍标「可使用」；
 * 3. 首单券只认已成交账单后，同一用户可用两张不同首单券并占两张未付订单。
 *
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class CouponOccupancyConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_checkout_available_list_excludes_coupon_with_pending_invoice(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();
        // 续费型账单被 InvoiceCleanupAutomationService 豁免超时自动清理，可长期滞留：
        // 这是「列表可选但结账必败」最持久的形态
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::UNPAID, 'renew');

        // 预留早已过期：只按 reserved_until 过滤会把这张券重新列为可选
        $userCoupon->forceFill(['reserved_until' => now()->subMinutes(11)])->save();

        $this->assertSame([], $this->availableFor($user, $product), '存在待支付账单的券不得出现在结账可用券列表');

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

    public function test_reserved_coupon_stays_inside_three_groups_and_shows_occupied(): void
    {
        [$user, $userCoupon] = $this->fixture();
        $userCoupon->forceFill(['reserved_until' => now()->addMinutes(10)])->save();

        $summary = app(CouponService::class)->summaryForUser($user);
        $item = $this->findOwnedItem($user, (int) $userCoupon->id);

        // 预留中的券此前不属于任何分组（三分组之和 0 < total 1），现在归「已用完」
        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['used_up']);
        $this->assertSame(0, $summary['available']);
        $this->assertSame(0, $summary['expired']);
        $this->assertSame(
            $summary['total'],
            $summary['available'] + $summary['used_up'] + $summary['expired'],
            '三分组必须与 total 完备不重不漏'
        );

        $this->assertSame('used_up', $item['status']);
        $this->assertSame('已占用', $item['status_label']);
        $this->assertFalse($item['can_use']);
    }

    public function test_coupon_with_pending_invoice_is_grouped_and_shown_as_occupied(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::UNPAID);
        $userCoupon->forceFill(['reserved_until' => now()->subMinutes(11)])->save();

        $summary = app(CouponService::class)->summaryForUser($user);
        $item = $this->findOwnedItem($user, (int) $userCoupon->id);

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['used_up']);
        $this->assertSame(
            $summary['total'],
            $summary['available'] + $summary['used_up'] + $summary['expired'],
            '挂账券必须落在某个分组内，不得三组皆空'
        );

        $this->assertSame('used_up', $item['status']);
        $this->assertSame('已占用', $item['status_label']);
        $this->assertSame('该优惠券已有待支付账单，请先完成支付或取消', $item['status_reason']);
    }

    public function test_cancelled_invoice_does_not_block_coupon(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();
        $invoice = $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::UNPAID);
        $userCoupon->forceFill(['reserved_until' => now()->subMinutes(11)])->save();

        // 因果断言：同一张券，挂账时不可选、账单取消后恢复可选，证明排除确由挂账引起
        $this->assertSame([], $this->availableFor($user, $product), '挂账期间不得可选');

        $invoice->forceFill(['status' => InvoiceStatus::CANCELLED])->save();

        $available = $this->availableFor($user, $product);
        $this->assertCount(1, $available, '账单取消后该券必须恢复可选');
        $this->assertSame((int) $userCoupon->id, (int) ($available[0]['id'] ?? 0));
        $this->assertSame('available', $this->findOwnedItem($user, (int) $userCoupon->id)['status']);
    }

    public function test_paid_invoice_without_sync_marks_coupon_used(): void
    {
        [$user, $userCoupon, $product] = $this->fixture();

        // 账单已支付但核销同步尚未回写 user_coupon：券仍 OWNED、预留已过期。
        // 占用拦截按「已有已支付账单」拒绝，展示/分组/列表必须同步认定为不可用。
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::PAID);
        $userCoupon->forceFill(['reserved_until' => now()->subMinutes(11)])->save();

        $summary = app(CouponService::class)->summaryForUser($user);
        $item = $this->findOwnedItem($user, (int) $userCoupon->id);

        $this->assertSame([], $this->availableFor($user, $product), '已支付未同步的券不得出现在结账可用券列表');
        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['used_up']);
        $this->assertSame(0, $summary['available']);
        $this->assertSame(
            $summary['total'],
            $summary['available'] + $summary['used_up'] + $summary['expired'],
            '已支付未同步的券必须落在「已用完」分组'
        );
        $this->assertSame('used_up', $item['status']);
        $this->assertSame('已使用', $item['status_label']);

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

    public function test_multi_coupon_summary_partitions_across_three_groups(): void
    {
        $user = User::factory()->create();

        $availableCoupon = $this->grantCouponTo($user);
        $occupiedCoupon = $this->grantCouponTo($user);
        $occupiedCoupon->forceFill(['reserved_until' => now()->addMinutes(10)])->save();
        $expiredCoupon = $this->grantCouponTo($user);
        Coupon::query()->whereKey((int) $expiredCoupon->coupon_id)->update(['expires_at' => now()->subDay()]);

        $summary = app(CouponService::class)->summaryForUser($user);

        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['available']);
        $this->assertSame(1, $summary['used_up']);
        $this->assertSame(1, $summary['expired']);
        $this->assertSame(
            $summary['total'],
            $summary['available'] + $summary['used_up'] + $summary['expired'],
            '多券并存时三分组仍须与 total 完备不重不漏'
        );

        $this->assertSame('available', $this->findOwnedItem($user, (int) $availableCoupon->id)['status']);
        $this->assertSame('used_up', $this->findOwnedItem($user, (int) $occupiedCoupon->id)['status']);
        $this->assertSame('expired', $this->findOwnedItem($user, (int) $expiredCoupon->id)['status']);
    }

    public function test_first_order_coupon_blocked_by_pending_first_order_order(): void
    {
        $user = User::factory()->create();
        $product = $this->product();

        $firstCoupon = $this->grantFirstOrderCoupon($user, 'A');
        $secondCoupon = $this->grantFirstOrderCoupon($user, 'B');

        // 首单券 A 占住一张未付订单
        $this->createInvoice($user, $firstCoupon, $product, InvoiceStatus::UNPAID);

        // 首单券 B：同用户已存在未付首单券订单，不得再占用，否则两张都支付即各享一次首单优惠
        $this->assertSame([], $this->availableFor($user, $product), '首单券不得在已有未付首单券订单时仍列为可选');

        $item = $this->findOwnedItem($user, (int) $secondCoupon->id);
        $this->assertSame('used_up', $item['status']);
        $this->assertSame('已用完', $item['status_label']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅限首单使用');

        app(CouponService::class)->reserveOwnedCouponForInvoice(
            (int) $secondCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            20.0,
            'new'
        );
    }

    public function test_pending_order_without_first_order_coupon_keeps_first_order_eligibility(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $coupon = $this->grantFirstOrderCoupon($user, 'C');

        // 普通未付订单（未消耗首单券）：首单资格不随之翻转
        $this->createInvoice($user, $coupon, $product, InvoiceStatus::UNPAID, 'new', withUserCoupon: false);

        $available = $this->availableFor($user, $product);

        $this->assertCount(1, $available);
        $this->assertSame((int) $coupon->id, (int) ($available[0]['id'] ?? 0));
    }

    /**
     * @return array{0: User, 1: UserCoupon, 2: Product}
     */
    private function fixture(): array
    {
        $user = User::factory()->create();

        return [$user, $this->grantCouponTo($user), $this->product()];
    }

    private function grantFirstOrderCoupon(User $user, string $suffix): UserCoupon
    {
        return $this->grantCouponTo($user, [
            'name' => '首单券'.$suffix.uniqid(),
            'code' => 'FO'.$suffix.uniqid(),
            'first_order_only' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function grantCouponTo(User $user, array $overrides = []): UserCoupon
    {
        $coupon = app(CouponService::class)->createCoupon(array_merge([
            'name' => '占用券'.uniqid(),
            'code' => 'OCC'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'min_amount' => 0,
            'status' => 1,
        ], $overrides), ['operator' => 'tester']);

        return UserCoupon::query()
            ->where('user_id', (int) $user->id)
            ->where('coupon_id', (int) $coupon['id'])
            ->firstOrFail();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function availableFor(User $user, Product $product): array
    {
        return app(CouponService::class)->availableCouponsForCheckout(
            (int) $user->id,
            $product,
            'monthly',
            20.0,
            'new'
        );
    }

    private function product(): Product
    {
        return Product::query()->create([
            'name' => '占用商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '20.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);
    }

    private function createInvoice(
        User $user,
        UserCoupon $userCoupon,
        Product $product,
        int $status,
        string $type = 'new',
        bool $withUserCoupon = true,
    ): Invoice {
        return Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'coupon_id' => $withUserCoupon ? (int) $userCoupon->coupon_id : null,
            'user_coupon_id' => $withUserCoupon ? (int) $userCoupon->id : null,
            'type' => $type,
            'amount' => '15.00',
            'paid_amount' => $status === InvoiceStatus::PAID ? '15.00' : '0.00',
            'billing_cycle' => 'monthly',
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::PAID ? now() : null,
            'due_date' => now()->addDay(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function findOwnedItem(User $user, int $userCouponId): array
    {
        $page = app(CouponService::class)->paginateForUser($user, [], 1, 50);

        foreach ($page['list'] as $item) {
            if ((int) ($item['id'] ?? 0) === $userCouponId) {
                return $item;
            }
        }

        $this->fail('未在持有券列表中找到目标优惠券 #'.$userCouponId);
    }
}
