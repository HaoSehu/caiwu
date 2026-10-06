<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\CouponCampaign;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\CouponCampaignService;
use App\Services\Finance\CouponService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 优惠券生命周期规则回归（批次 B：首单口径、用户端分组、启停、删除保护、活动停用）。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class CouponLifecycleRuleTest extends TestCase
{
    use DatabaseTransactions;

    public function test_first_order_eligibility_ignores_recharge_and_unpaid_invoices(): void
    {
        [$user, $userCoupon, $product] = $this->firstOrderFixture();

        // 充值账单不算「下过单」：首单券仍可用
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::PAID, 'recharge');
        $this->assertCouponPreviewable($user, $userCoupon, $product);

        // 未支付的新购账单不构成成交：首单资格不随其翻转
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::UNPAID, 'new');
        $this->assertCouponPreviewable($user, $userCoupon, $product);

        // 真实新购已成交：首单资格耗尽
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::PAID, 'new');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅限首单使用');

        app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );
    }

    public function test_first_order_eligibility_counts_normal_type_paid_invoice(): void
    {
        [$user, $userCoupon, $product] = $this->firstOrderFixture();

        // createFromOrder 对新购订单落 type='normal'（InvoiceService match default），
        // 管理员开单成交的真实购买必须计入「下过单」，否则首单限制被绕过
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::PAID, 'normal');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅限首单使用');

        app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );
    }

    public function test_owned_coupon_summary_assigns_used_coupons_to_used_up_group(): void
    {
        $user = User::factory()->create();

        $usedCoupon = $this->grantCoupon($user, 1);
        $usedCoupon->forceFill(['status' => 2])->save();

        $expiredCoupon = $this->grantCoupon($user, 2);
        Coupon::query()->whereKey((int) $expiredCoupon->coupon_id)->update(['expires_at' => now()->subDay()]);

        $summary = app(CouponService::class)->summaryForUser($user);

        // 已使用的券必须归「已用完」而不是「已过期」；自身失效的持有券归「已过期」
        $this->assertSame(1, $summary['used_up']);
        $this->assertSame(1, $summary['expired']);
        $this->assertSame(0, $summary['available']);
    }

    public function test_owned_coupon_summary_groups_remain_exhaustive_for_used_coupon_of_disabled_coupon(): void
    {
        $user = User::factory()->create();

        // 已使用 × 券已停用：三分组必须仍然完备（归「已用完」，不得三组皆空）
        $orphanUsedCoupon = $this->grantCoupon($user, 3);
        $orphanUsedCoupon->forceFill(['status' => 2])->save();
        Coupon::query()->whereKey((int) $orphanUsedCoupon->coupon_id)->update(['status' => 0]);

        $summary = app(CouponService::class)->summaryForUser($user);

        $this->assertSame(1, $summary['used_up']);
        $this->assertSame(0, $summary['expired']);
        $this->assertSame(0, $summary['available']);
        $this->assertSame(1, $summary['total']);
    }

    public function test_toggle_coupon_status_flips_both_ways(): void
    {
        [$user, $userCoupon] = $this->firstOrderFixture();
        $couponId = (int) $userCoupon->coupon_id;
        $service = app(CouponService::class);
        $coupon = Coupon::query()->findOrFail($couponId);

        $disabled = $service->toggleCouponStatus($coupon, ['operator' => 'tester']);
        $this->assertSame(0, (int) $disabled['status']);

        $enabled = $service->toggleCouponStatus($coupon, ['operator' => 'tester']);
        $this->assertSame(1, (int) $enabled['status']);
    }

    public function test_delete_coupon_rejects_when_pending_invoice_references_it(): void
    {
        [$user, $userCoupon, $product] = $this->firstOrderFixture();
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::UNPAID, 'new', (int) $userCoupon->coupon_id);
        $coupon = Coupon::query()->findOrFail((int) $userCoupon->coupon_id);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仍有关联订单或账单');

        app(CouponService::class)->deleteCoupon($coupon, ['operator' => 'tester']);
    }

    public function test_deactivated_campaign_is_not_dispatched(): void
    {
        $campaignService = app(CouponCampaignService::class);
        $campaign = $campaignService->createCampaign($this->campaignPayload(), ['operator' => 'tester']);

        // 先停用再调度：已停用活动不得发放批次（遍历筛选与锁内重读双重拦截）
        $campaignService->toggleCampaignStatus(
            CouponCampaign::query()->findOrFail((int) $campaign['id']),
            ['operator' => 'tester']
        );

        $campaignService->dispatchDueCampaigns();

        $this->assertSame(0, Coupon::query()->where('coupon_campaign_id', (int) $campaign['id'])->count());
    }

    public function test_deactivated_campaign_is_skipped_inside_dispatch_lock(): void
    {
        $campaignService = app(CouponCampaignService::class);
        $campaign = $campaignService->createCampaign($this->campaignPayload(), ['operator' => 'tester']);
        $model = CouponCampaign::query()->findOrFail((int) $campaign['id']);
        // 模拟「遍历筛选之后、行锁之前」活动被停用：锁内重读守卫必须拦下这最后一批
        $model->forceFill(['status' => 0])->save();

        $method = new \ReflectionMethod(CouponCampaignService::class, 'dispatchSingleCampaign');
        $result = $method->invoke(
            $campaignService,
            $model,
            CarbonImmutable::now(config('app.timezone')),
            'test-lock-'.uniqid(),
            ['operator' => 'tester'],
            true
        );

        $this->assertNull($result);
        $this->assertSame(0, Coupon::query()->where('coupon_campaign_id', (int) $campaign['id'])->count());
    }

    public function test_dispatched_coupon_validity_counts_from_actual_generation(): void
    {
        $campaignService = app(CouponCampaignService::class);
        $campaign = $campaignService->createCampaign(array_merge($this->campaignPayload(), [
            'valid_duration_hours' => 1,
        ]), ['operator' => 'tester']);

        // 模拟「调度中断后次日补发」：计划时刻取昨天同一时刻（早已到期未发），
        // 有效期必须从实际生成时刻起算——按计划时刻起算的批次此刻已是过期券
        $method = new \ReflectionMethod(CouponCampaignService::class, 'dispatchSingleCampaign');
        $result = $method->invoke(
            $campaignService,
            CouponCampaign::query()->findOrFail((int) $campaign['id']),
            CarbonImmutable::yesterday(config('app.timezone')),
            'test-catchup-'.uniqid(),
            ['operator' => 'tester'],
            true
        );

        $this->assertNotNull($result);
        $coupon = Coupon::query()->whereKey((int) $result['coupon']['id'])->firstOrFail();
        $this->assertTrue($coupon->expires_at->gt(now()), '补发批次有效期应从实际生成时刻起算');
    }

    /**
     * @return array{0: User, 1: UserCoupon, 2: Product}
     */
    private function firstOrderFixture(): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => '首单规则商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '100.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);
        $coupon = app(CouponService::class)->createCoupon([
            'name' => '首单券'.uniqid(),
            'code' => 'FIRST'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'min_amount' => 0,
            'first_order_only' => 1,
            'status' => 1,
        ], ['operator' => 'tester']);
        $userCoupon = UserCoupon::query()
            ->where('user_id', (int) $user->id)
            ->where('coupon_id', (int) $coupon['id'])
            ->firstOrFail();

        return [$user, $userCoupon, $product];
    }

    private function grantCoupon(User $user, int $suffix): UserCoupon
    {
        app(CouponService::class)->createCoupon([
            'name' => '分组券'.$suffix.uniqid(),
            'code' => 'GROUP'.$suffix.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'min_amount' => 0,
            'status' => 1,
        ], ['operator' => 'tester']);

        return UserCoupon::query()
            ->where('user_id', (int) $user->id)
            ->whereIn('coupon_id', Coupon::query()->select('id'))
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignPayload(): array
    {
        return [
            'name' => '规则活动'.uniqid(),
            'weekdays' => [(int) now()->dayOfWeek],
            'trigger_time' => '00:00',
            'issue_quantity' => 2,
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'status' => 1,
        ];
    }

    private function createInvoice(User $user, UserCoupon $userCoupon, Product $product, int $status, string $type, ?int $couponId = null): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'coupon_id' => $couponId,
            'type' => $type,
            'amount' => '100.00',
            'paid_amount' => $status === InvoiceStatus::PAID ? '100.00' : '0.00',
            'billing_cycle' => 'monthly',
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::PAID ? now() : null,
            'due_date' => now()->addDay(),
        ]);
    }

    private function assertCouponPreviewable(User $user, UserCoupon $userCoupon, Product $product): void
    {
        $threw = false;
        try {
            app(CouponService::class)->previewOwnedCoupon(
                (int) $userCoupon->id,
                (int) $user->id,
                $product,
                'monthly',
                100.0,
                'new'
            );
        } catch (BusinessException) {
            $threw = true;
        }

        $this->assertFalse($threw, '首单资格判定不应被充值/未支付账单污染');
    }
}
