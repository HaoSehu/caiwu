<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\CouponCampaign;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
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

    /**
     * P2-6：仅新客首单 × 仅老客 语义互斥，创建时直接拦截。
     */
    public function test_create_coupon_rejects_first_order_only_with_only_old_client(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('「仅首单」与「仅老客」互斥');

        app(CouponService::class)->createCoupon([
            'name' => '互斥定向券'.uniqid(),
            'code' => 'OO'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'first_order_only' => true,
            'only_old_client' => true,
            'status' => 1,
        ], ['operator' => 'tester']);
    }

    /**
     * P2-6：仅老客券——无成交账单的用户使用被拒。
     */
    public function test_only_old_client_rejects_user_without_paid_order(): void
    {
        [$user, $userCoupon, $product] = $this->targetingFixture(['only_old_client' => true]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅限老客户使用');

        app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );
    }

    /**
     * P2-6：已有成交账单（PAID 新购）的老客可正常使用。
     */
    public function test_only_old_client_allows_user_with_paid_order(): void
    {
        [$user, $userCoupon, $product] = $this->targetingFixture(['only_old_client' => true]);
        $this->createInvoice($user, $userCoupon, $product, InvoiceStatus::PAID, 'new');

        $payload = app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );

        $this->assertIsArray($payload);
        $this->assertSame('5.00', (string) ($payload['discount_amount'] ?? ''));
    }

    /**
     * P2-6：领取侧同口径——新用户不能领取仅老客公开券。
     */
    public function test_claim_rejects_only_old_client_public_coupon_for_new_user(): void
    {
        $user = User::factory()->create();
        $coupon = app(CouponService::class)->createCoupon([
            'name' => '仅老客公开券'.uniqid(),
            'code' => 'OLDP'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'only_old_client' => true,
            'status' => 1,
        ], ['operator' => 'tester']);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅限老客户领取');

        app(CouponService::class)->claimPublicCoupon($user, Coupon::query()->findOrFail((int) $coupon['id']));
    }

    /**
     * P2-4：前置持有条件——用户名下无任一指定产品的运行中服务时使用被拒。
     */
    public function test_requires_product_ids_reject_user_without_active_service(): void
    {
        $holderProduct = Product::query()->create([
            'name' => '前置持有商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '50.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);
        [$user, $userCoupon, $product] = $this->targetingFixture([], [
            'requires_product_ids' => [$holderProduct->id],
        ]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('需持有指定产品');

        app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );
    }

    /**
     * P2-4：名下持有任一指定产品的运行中服务即可使用。
     */
    public function test_requires_product_ids_allow_user_with_active_service(): void
    {
        $holderProduct = Product::query()->create([
            'name' => '前置持有商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '50.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);
        [$user, $userCoupon, $product] = $this->targetingFixture([], [
            'requires_product_ids' => [$holderProduct->id],
        ]);
        Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $holderProduct->id,
            'billing_cycle' => 'monthly',
            'amount' => 50.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $payload = app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new'
        );

        $this->assertIsArray($payload);
    }

    /**
     * P3-11：is_discount=0 且本单已享受会员折扣减免 → 券不应用。
     */
    public function test_is_discount_exclusive_blocks_coupon_with_member_discount(): void
    {
        [$user, $userCoupon, $product] = $this->targetingFixture(['is_discount' => false]);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('不可与会员折扣同时使用');

        app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new',
            true
        );
    }

    /**
     * P3-11：无会员折扣减免时互斥券照常应用；默认可叠加券不受 memberDiscountApplied 影响。
     */
    public function test_is_discount_exclusive_allows_without_member_discount(): void
    {
        [$user, $userCoupon, $product] = $this->targetingFixture(['is_discount' => false]);

        $payload = app(CouponService::class)->previewOwnedCoupon(
            (int) $userCoupon->id,
            (int) $user->id,
            $product,
            'monthly',
            100.0,
            'new',
            false
        );

        $this->assertIsArray($payload);
        $this->assertSame('5.00', (string) ($payload['discount_amount'] ?? ''));
    }

    /**
     * 活动生成的券继承定向规则（only_old_client/is_discount/requires_product_ids 透传链路）：
     * 新用户领取活动生成的仅老客券后，使用侧校验必须生效。
     */
    public function test_campaign_generated_coupon_inherits_targeting_rules(): void
    {
        $user = User::factory()->create();
        $campaign = app(CouponCampaignService::class)->createCampaign([
            'name' => '定向活动'.uniqid(),
            'weekdays' => [(int) now()->dayOfWeek],
            'trigger_time' => '00:00',
            'issue_quantity' => 2,
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'only_old_client' => true,
            'is_discount' => false,
            'status' => 1,
        ], ['operator' => 'tester']);

        // 触发到期发放（活动配置为今天 + 00:00，必然到期；DatabaseTransactions 回滚无残留）
        app(CouponCampaignService::class)->dispatchDueCampaigns(CarbonImmutable::now(), ['operator' => 'tester']);

        $generatedCoupon = Coupon::query()
            ->where('coupon_campaign_id', (int) $campaign['id'])
            ->orderByDesc('id')
            ->firstOrFail();

        $this->assertTrue((bool) $generatedCoupon->only_old_client, '活动生成的券必须继承仅老客规则');
        $this->assertFalse((bool) $generatedCoupon->is_discount, '活动生成的券必须继承叠加开关');
    }

    /**
     * 定向/叠加规则用例的公共夹具。
     *
     * @param  array<string, mixed>  $couponOverrides
     * @param  array<string, mixed>  $extraPayload
     * @return array{0: User, 1: UserCoupon, 2: Product}
     */
    private function targetingFixture(array $couponOverrides = [], array $extraPayload = []): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => '定向规则商品'.uniqid(),
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
            'name' => '定向券'.uniqid(),
            'code' => 'TG'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'min_amount' => 0,
            'status' => 1,
            ...$couponOverrides,
            ...$extraPayload,
        ], ['operator' => 'tester']);
        $userCoupon = UserCoupon::query()
            ->where('user_id', (int) $user->id)
            ->where('coupon_id', (int) $coupon['id'])
            ->firstOrFail();

        return [$user, $userCoupon, $product];
    }

    /**
     * 首单券 × 仅续费 是静默死配置：创建/更新时直接拦截（P3-8 修复回归）。
     */
    public function test_create_coupon_rejects_first_order_only_with_renew_scope(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('首单券不支持「仅续费」');

        app(CouponService::class)->createCoupon([
            'name' => '死配置券'.uniqid(),
            'code' => 'DEAD'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'renew',
            'discount_value' => 5,
            'first_order_only' => true,
            'status' => 1,
        ], ['operator' => 'tester']);
    }

    /**
     * 首单券 × 持续优惠（新购+续费）是合法组合：新购侧可正常使用，不得误拦。
     */
    public function test_create_coupon_allows_first_order_only_with_recurring_scope(): void
    {
        $coupon = app(CouponService::class)->createCoupon([
            'name' => '首单持续券'.uniqid(),
            'code' => 'FIRST'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'recurring',
            'discount_value' => 5,
            'first_order_only' => true,
            'status' => 1,
        ], ['operator' => 'tester']);

        $this->assertTrue((bool) Coupon::query()->findOrFail($coupon['id'])->first_order_only);
    }

    /**
     * 活动配置侧同口径拦截：避免到定时发放时才失败（发放失败导致当日批次丢失）。
     */
    public function test_create_campaign_rejects_first_order_only_with_renew_scope(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('首单券不支持「仅续费」');

        app(CouponCampaignService::class)->createCampaign([
            'name' => '死配置活动'.uniqid(),
            'weekdays' => [1],
            'trigger_time' => '09:00',
            'issue_quantity' => 10,
            'discount_type' => 'fixed',
            'discount_scope' => 'renew',
            'discount_value' => 5,
            'first_order_only' => true,
            'status' => 1,
        ], ['operator' => 'tester']);
    }

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
