<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\CouponStatus;
use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Models\Invoice;
use App\Models\MarketingProductGroup;
use App\Models\MemberLevel;
use App\Models\MemberLevelGroupDiscount;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\CouponService;
use App\Services\Provisioning\ProvisionService;
use App\Services\Provisioning\ServiceRenewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 会员折扣与续费定价基数隔离回归：折后价不得进入续费定价。
 *
 * 修复前 services.amount 在开通/续费成交时被写成会员折后价，续费再对它打折，
 * 产生 0.75^n 式复利衰减（11.90 → 8.92 → 6.69 → 5.02）；普通用户 100% 折扣无损故被掩盖。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class RenewPricingMemberDiscountCompoundingTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 代理用户连续两轮续费：基数必须恒为目录价，应付价逐轮相等（幂等）。
     * 场景：目录价 11.90，代理等级 discount_value=75（bates 语义，折后保留 75%）。
     */
    public function test_agent_renew_rounds_keep_catalog_base(): void
    {
        [$user, $service] = $this->agentFixture(11.90, 75);
        $renew = app(ServiceRenewService::class);

        $invoice1 = $renew->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');
        $this->assertSame('2.98', number_format((float) $invoice1->member_discount_amount, 2, '.', ''));
        $this->assertSame('8.92', number_format((float) $invoice1->amount, 2, '.', ''));

        $this->payAndFulfill($invoice1);

        // 核心回归点：续费成交后服务金额必须回到目录价 11.90（修复前写回折后价 8.92）
        $this->assertSame('11.90', (string) $service->refresh()->amount);

        // 第二轮：把到期时间拨到已过期（与真实到期续费一致）以绕开同周期防双扣闸门
        $service->forceFill(['expires_at' => now()->subDay()])->save();
        $invoice2 = $renew->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');
        // 修复前基数已被污染为 8.92，第二轮应付会衰减为 6.69
        $this->assertSame('8.92', number_format((float) $invoice2->amount, 2, '.', ''));

        $this->payAndFulfill($invoice2);
        $this->assertSame('11.90', (string) $service->refresh()->amount);
    }

    /**
     * 普通用户（折扣规则未命中）：全程目录价，修复不得影响无折扣路径。
     */
    public function test_normal_user_renew_keeps_full_price(): void
    {
        [$user, $service] = $this->plainFixture(11.90);

        $invoice = app(ServiceRenewService::class)->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');
        $this->assertSame('0.00', number_format((float) $invoice->member_discount_amount, 2, '.', ''));
        $this->assertSame('11.90', number_format((float) $invoice->amount, 2, '.', ''));

        $this->payAndFulfill($invoice);
        $this->assertSame('11.90', (string) $service->refresh()->amount);
    }

    /**
     * 新购开通（账单直开路径）：services.amount 与 locked_pricing 基数必须取目录价，
     * 不能落折后应付价（修复前落 8.92，成为续费复利的污染入口）。
     */
    public function test_new_purchase_provision_seeds_catalog_amount(): void
    {
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '11.90'],
        ]);
        $user = User::factory()->create();
        // processPaidInvoice 的存量分支按 'normal' 识别无订单新购账单
        $invoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => 'normal',
            'amount' => 8.92,
            'discount' => 0,
            'member_discount_amount' => 2.98,
            'status' => InvoiceStatus::PAID,
            'billing_cycle' => 'monthly',
            'paid_at' => now(),
        ]);

        $service = app(ProvisionService::class)->processPaidInvoice($invoice);

        $this->assertInstanceOf(Service::class, $service);
        $this->assertSame('11.90', (string) $service->amount);
        $locked = $service->locked_pricing ?? [];
        $this->assertSame('11.90', (string) ($locked['monthly']['base_amount'] ?? ''));
    }

    /**
     * 新购订单路径开通：shadow order 的 amount 是应付折后价（与新购账单同口径），
     * 开通基数必须经目录价还原，不能直接落 order.amount（修复前落 8.92）。
     */
    public function test_order_path_provision_seeds_catalog_amount(): void
    {
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '11.90'],
        ]);
        $user = User::factory()->create();
        $order = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => 'new',
            'amount' => 8.92,
            'discount' => 0,
            'member_discount_amount' => 2.98,
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'status' => OrderStatus::PENDING,
        ]);

        $service = app(ProvisionService::class)->processPaidOrder($order);

        $this->assertInstanceOf(Service::class, $service);
        $this->assertSame('11.90', (string) $service->amount);
        $locked = $service->locked_pricing ?? [];
        $this->assertSame('11.90', (string) ($locked['monthly']['base_amount'] ?? ''));
    }

    /**
     * 会员折扣 + 优惠券叠加续费：三项还原恒等目录价，成交写回不得漏加会员折扣。
     */
    public function test_agent_renew_with_coupon_restores_catalog_base(): void
    {
        [$user, $service] = $this->agentFixture(11.90, 75);

        $coupon = app(CouponService::class)->createCoupon([
            'name' => '续费减一元'.uniqid(),
            'code' => 'RC'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'recurring',
            'discount_value' => 1,
            'status' => CouponStatus::ACTIVE,
        ], ['operator' => 'tester']);
        $userCoupon = UserCoupon::query()
            ->where('user_id', $user->id)
            ->where('coupon_id', (int) $coupon['id'])
            ->first();
        $this->assertNotNull($userCoupon);

        $invoice = app(ServiceRenewService::class)->createRenewInvoiceForUser(
            $user,
            (int) $service->id,
            'monthly',
            (int) $userCoupon->id
        );
        $this->assertSame('2.98', number_format((float) $invoice->member_discount_amount, 2, '.', ''));
        $this->assertSame('1.00', number_format((float) $invoice->discount, 2, '.', ''));
        $this->assertSame('7.92', number_format((float) $invoice->amount, 2, '.', ''));

        $this->payAndFulfill($invoice);

        // 修复前写回 invoice.amount + discount = 8.92，会员折扣被丢掉
        $this->assertSame('11.90', (string) $service->refresh()->amount);
    }

    /**
     * 置为已支付并驱动续费履约（与支付回调后的履约入口一致）。
     */
    private function payAndFulfill(Invoice $invoice): void
    {
        $invoice->forceFill([
            'status' => InvoiceStatus::PAID,
            'paid_amount' => $invoice->amount,
            'paid_at' => now(),
        ])->save();

        app(ServiceRenewService::class)->processPaidRenewInvoice($invoice->fresh());
    }

    /**
     * @return array{0: User, 1: Service}
     */
    private function agentFixture(float $catalogAmount, float $keepRatio): array
    {
        $level = MemberLevel::query()->create([
            'name' => '代理等级'.uniqid(),
            'status' => 1,
        ]);
        $user = User::factory()->create(['member_level_id' => $level->id]);
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => number_format($catalogAmount, 2, '.', '')],
        ]);

        $group = MarketingProductGroup::query()->create(['name' => 'g'.uniqid(), 'sort_order' => 0]);
        $group->items()->create(['product_id' => $product->id]);
        MemberLevelGroupDiscount::query()->create([
            'member_level_id' => $level->id,
            'marketing_product_group_id' => $group->id,
            'discount_type' => MemberLevelGroupDiscount::TYPE_PERCENT,
            'discount_value' => $keepRatio,
        ]);

        return [$user, $this->makeService($user, $product, $catalogAmount)];
    }

    /**
     * @return array{0: User, 1: Service}
     */
    private function plainFixture(float $catalogAmount): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => number_format($catalogAmount, 2, '.', '')],
        ]);

        return [$user, $this->makeService($user, $product, $catalogAmount)];
    }

    private function makeService(User $user, Product $product, float $catalogAmount): Service
    {
        return Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => $catalogAmount,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
    }
}
