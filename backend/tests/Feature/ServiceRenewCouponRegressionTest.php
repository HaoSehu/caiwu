<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\Finance\CouponService;
use App\Services\Provisioning\ProvisionService;
use App\Services\Provisioning\ServiceRenewService;
use App\Support\Money;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 续费用券回归（重建 9d998ea3 删除的 ServiceRenewCouponRegressionTest）。
 *
 * P0 修复回归：
 * - 同周期续费防重不得按 user_coupon_id 精确匹配（换券/去券必须取消重建而非并存）；
 * - 续费订单金额口径与账单一致（应付价），目录价经 Money::catalogAmountOf 还原；
 * - 已支付未履约续费账单的防双扣拦截不受券选择影响。
 * P1-1 持续优惠回归：服务绑定快照自动折算、与手选券互斥、报价展示、开通绑定落点。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染 idc_test 现有数据。
 */
class ServiceRenewCouponRegressionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_renew_with_different_coupon_replaces_pending_invoice(): void
    {
        [$user, $service, $couponA, $couponB] = $this->fixture();
        $userCouponA = $this->userCouponOf($user, $couponA);
        $userCouponB = $this->userCouponOf($user, $couponB);
        $renew = app(ServiceRenewService::class);

        $invoice1 = $renew->createRenewInvoiceForUser($user, (int) $service->id, 'monthly', (int) $userCouponA->id);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice1->refresh()->status);
        $this->assertSame('5.00', number_format((float) $invoice1->discount, 2, '.', ''));

        // 换券请求：旧账单必须被取消重建，而不是与带券 A 的账单并存（并存即可双重支付）
        $invoice2 = $renew->createRenewInvoiceForUser($user, (int) $service->id, 'monthly', (int) $userCouponB->id);

        $this->assertNotSame((int) $invoice1->id, (int) $invoice2->id);
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice1->refresh()->status);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice2->refresh()->status);
        $this->assertSame((int) $userCouponB->id, (int) $invoice2->user_coupon_id);
        $this->assertSame('3.00', number_format((float) $invoice2->discount, 2, '.', ''));
        $this->assertSame('17.00', number_format((float) $invoice2->amount, 2, '.', ''));

        $pendingCount = Invoice::query()
            ->where('service_id', (int) $service->id)
            ->where('type', 'renew')
            ->where('billing_cycle', 'monthly')
            ->where('status', InvoiceStatus::UNPAID)
            ->count();
        $this->assertSame(1, $pendingCount, '同周期不允许并存多张待支付续费账单');
    }

    public function test_renew_without_coupon_replaces_couponed_pending_invoice(): void
    {
        [$user, $service, $couponA] = $this->fixture();
        $userCouponA = $this->userCouponOf($user, $couponA);
        $renew = app(ServiceRenewService::class);

        $invoice1 = $renew->createRenewInvoiceForUser($user, (int) $service->id, 'monthly', (int) $userCouponA->id);
        $invoice2 = $renew->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');

        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice1->refresh()->status);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice2->refresh()->status);
        $this->assertEmpty($invoice2->user_coupon_id);
        $this->assertSame('20.00', number_format((float) $invoice2->amount, 2, '.', ''));
    }

    public function test_renew_with_same_coupon_reuses_pending_invoice(): void
    {
        [$user, $service, $couponA] = $this->fixture();
        $userCouponA = $this->userCouponOf($user, $couponA);
        $renew = app(ServiceRenewService::class);

        $invoice1 = $renew->createRenewInvoiceForUser($user, (int) $service->id, 'monthly', (int) $userCouponA->id);

        // 同券同周期重复提交：必须复用同一张待支付账单。券占用拦截只作用于「再次占用」，
        // 不得让复用路径（previewOwnedCoupon 被复用参数比较直接调用）抛错整单失败。
        $invoice2 = $renew->createRenewInvoiceForUser($user, (int) $service->id, 'monthly', (int) $userCouponA->id);

        $this->assertSame((int) $invoice1->id, (int) $invoice2->id);
        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice1->refresh()->status);
        $this->assertSame(1, Invoice::query()
            ->where('service_id', (int) $service->id)
            ->where('type', 'renew')
            ->where('status', InvoiceStatus::UNPAID)
            ->count());
    }

    public function test_renew_order_amount_matches_invoice_payable(): void
    {
        [$user, $service, $couponA] = $this->fixture();
        $userCouponA = $this->userCouponOf($user, $couponA);

        $invoice = app(ServiceRenewService::class)->createRenewInvoiceForUser(
            $user,
            (int) $service->id,
            'monthly',
            (int) $userCouponA->id
        );

        // 订单金额必须与账单同口径（应付价）；目录价从账单侧经 catalogAmountOf 还原
        $order = Order::query()->findOrFail((int) $invoice->order_id);
        $this->assertSame('15.00', number_format((float) $order->amount, 2, '.', ''));
        $this->assertSame('15.00', number_format((float) $invoice->amount, 2, '.', ''));
        $this->assertSame('5.00', number_format((float) $order->discount, 2, '.', ''));
        $this->assertSame('20.00', number_format(Money::catalogAmountOf($invoice), 2, '.', ''));
    }

    public function test_blocking_paid_renew_invoice_ignores_coupon_selection(): void
    {
        [$user, $service, $couponA, $couponB] = $this->fixture();
        $userCouponB = $this->userCouponOf($user, $couponB);

        // 同周期已有已支付未履约续费账单（带券 A）：换券 B 再请求仍必须被拦截复用
        $paidInvoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'product_id' => (int) $service->product_id,
            'service_id' => (int) $service->id,
            'type' => 'renew',
            'amount' => '20.00',
            'paid_amount' => '20.00',
            'billing_cycle' => 'monthly',
            'coupon_id' => (int) $couponA['id'],
            'user_coupon_id' => (int) $this->userCouponOf($user, $couponA)->id,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'due_date' => now()->addDay(),
        ]);

        $result = app(ServiceRenewService::class)->createRenewInvoiceForUser(
            $user,
            (int) $service->id,
            'monthly',
            (int) $userCouponB->id
        );

        $this->assertSame((int) $paidInvoice->id, (int) $result->id);
        $this->assertSame(0, Invoice::query()
            ->where('service_id', (int) $service->id)
            ->where('type', 'renew')
            ->where('status', InvoiceStatus::UNPAID)
            ->count());
    }

    public function test_renew_success_writes_catalog_amount_to_service(): void
    {
        [$user, $service, $couponA] = $this->fixture();
        $userCouponA = $this->userCouponOf($user, $couponA);

        $invoice = app(ServiceRenewService::class)->createRenewInvoiceForUser(
            $user,
            (int) $service->id,
            'monthly',
            (int) $userCouponA->id
        );

        // 订单改写应付价后，成交回写 services.amount 必须经目录价还原（防复利衰减复发）
        $invoice->forceFill([
            'status' => InvoiceStatus::PAID,
            'paid_amount' => $invoice->amount,
            'paid_at' => now(),
        ])->save();
        app(ServiceRenewService::class)->processPaidRenewInvoice($invoice->fresh());

        $this->assertSame('20.00', (string) $service->refresh()->amount);
    }

    /**
     * P1-1 持续优惠（参照 ZJMF recurring/host.promoid 快照口径）：
     * 服务绑定优惠快照后，续费不传券也自动按快照折算（营销口径「每次续费均按优惠价」）。
     */
    public function test_service_binding_applies_recurring_discount_without_user_coupon(): void
    {
        [$user, $service, $couponA] = $this->fixture();
        $this->bindRenewCoupon($service, $couponA, 'fixed', 5.0);

        $renew = app(ServiceRenewService::class);
        $invoice = $renew->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');

        $this->assertSame('5.00', number_format((float) $invoice->discount, 2, '.', ''));
        $this->assertSame('15.00', number_format((float) $invoice->amount, 2, '.', ''));
        $this->assertSame((int) $couponA['id'], (int) $invoice->coupon_id);
        $this->assertEmpty($invoice->user_coupon_id, '持续优惠是服务级快照权利，不占用 user_coupon 实例');

        // 同参数重复提交：与手选券一致复用未付账单（对账口径含绑定优惠）
        $invoice2 = $renew->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');
        $this->assertSame((int) $invoice->id, (int) $invoice2->id);
    }

    /**
     * 绑定与手选券互斥：防止绑定优惠 + 手选券双重折价。
     */
    public function test_service_binding_rejects_extra_user_coupon(): void
    {
        [$user, $service, $couponA, $couponB] = $this->fixture();
        $this->bindRenewCoupon($service, $couponA, 'fixed', 5.0);
        $userCouponB = $this->userCouponOf($user, $couponB);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('不可再叠加其他优惠券');

        app(ServiceRenewService::class)->createRenewInvoiceForUser(
            $user,
            (int) $service->id,
            'monthly',
            (int) $userCouponB->id
        );
    }

    /**
     * percentage 快照折算回归：discount_value=90 → 9 折（20 元基数减免 2 元实付 18）。
     */
    public function test_service_binding_percentage_snapshot_discount(): void
    {
        [$user, $service, $couponA] = $this->fixture();
        $this->bindRenewCoupon($service, $couponA, 'percentage', 90.0);

        $invoice = app(ServiceRenewService::class)->createRenewInvoiceForUser($user, (int) $service->id, 'monthly');

        $this->assertSame('2.00', number_format((float) $invoice->discount, 2, '.', ''));
        $this->assertSame('18.00', number_format((float) $invoice->amount, 2, '.', ''));
    }

    /**
     * 报价展示：绑定后 available_coupons 清空（不提供手选券）、下发 renew_coupon_binding、
     * 周期价格按快照折算。
     */
    public function test_renew_preview_exposes_binding_and_hides_coupon_picker(): void
    {
        [$user, $service, $couponA] = $this->fixture();
        $this->bindRenewCoupon($service, $couponA, 'fixed', 5.0);

        $preview = app(ServiceRenewService::class)->previewForUser($user, (int) $service->id, 'monthly');

        $this->assertSame([], $preview['available_coupons']);
        $this->assertNull($preview['selected_coupon']);
        $this->assertSame((int) $couponA['id'], (int) ($preview['renew_coupon_binding']['coupon_id'] ?? 0));
        $monthly = collect($preview['cycles'])->firstWhere('billing_cycle', 'monthly');
        $this->assertNotNull($monthly);
        $this->assertSame('15.00', (string) $monthly['amount']);
        $this->assertSame('5.00', (string) $monthly['discount_amount']);
    }

    /**
     * 绑定落点回归：新购账单使用 recurring 券开通服务时落下快照（ProvisionService）。
     */
    public function test_paid_new_invoice_with_recurring_coupon_binds_service(): void
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => '绑定落点商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '20.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);
        $coupon = app(CouponService::class)->createCoupon([
            'name' => '八折持续'.uniqid(),
            'code' => 'RRC'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'percentage',
            'discount_scope' => 'recurring',
            'discount_value' => 80,
            'min_amount' => 0,
            'status' => 1,
        ], ['operator' => 'tester']);

        $couponRow = Coupon::query()->findOrFail((int) $coupon['id']);
        $invoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'type' => 'normal',
            'amount' => '16.00',
            'paid_amount' => '16.00',
            'billing_cycle' => 'monthly',
            'coupon_id' => (int) $coupon['id'],
            'coupon_code' => (string) $couponRow->code,
            'coupon_snapshot' => [
                'id' => (int) $coupon['id'],
                'code' => (string) $couponRow->code,
                'name' => (string) $coupon['name'],
                'discount_scope' => 'recurring',
                'discount_type' => 'percentage',
                'discount_value' => '80.00',
                'discount_label' => '8 折优惠',
            ],
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'due_date' => now()->addDay(),
        ]);

        $service = app(ProvisionService::class)->processPaidInvoice($invoice);

        $this->assertInstanceOf(Service::class, $service);
        $binding = $service->renewCouponBinding();
        $this->assertNotNull($binding, 'recurring 券开通服务必须落下持续优惠绑定');
        $this->assertSame((int) $coupon['id'], $binding['coupon_id']);
        $this->assertSame('percentage', $binding['discount_type']);
        $this->assertSame(80.0, $binding['discount_value']);

        // 对照组：非 recurring 券不落绑定
        $couponFirst = app(CouponService::class)->createCoupon([
            'name' => '新购立减'.uniqid(),
            'code' => 'RNF'.uniqid(),
            'distribution_type' => 'public',
            'discount_type' => 'fixed',
            'discount_scope' => 'first_month',
            'discount_value' => 5,
            'min_amount' => 0,
            'status' => 1,
        ], ['operator' => 'tester']);
        $invoice2 = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'type' => 'normal',
            'amount' => '15.00',
            'paid_amount' => '15.00',
            'billing_cycle' => 'monthly',
            'coupon_id' => (int) $couponFirst['id'],
            'coupon_code' => (string) Coupon::query()->findOrFail((int) $couponFirst['id'])->code,
            'coupon_snapshot' => [
                'id' => (int) $couponFirst['id'],
                'code' => (string) Coupon::query()->findOrFail((int) $couponFirst['id'])->code,
                'name' => (string) $couponFirst['name'],
                'discount_scope' => 'first_month',
                'discount_type' => 'fixed',
                'discount_value' => '5.00',
                'discount_label' => '立减 ¥5',
            ],
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'due_date' => now()->addDay(),
        ]);
        $service2 = app(ProvisionService::class)->processPaidInvoice($invoice2);

        $this->assertNull($service2->renewCouponBinding(), 'first_month 券为一次性权利，不得绑定服务');
    }

    /**
     * @return array{0: User, 1: Service, 2: array, 3: array}
     */
    private function fixture(): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'name' => '续费回归商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '20.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);
        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 20.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $couponA = app(CouponService::class)->createCoupon([
            'name' => '续费减五'.uniqid(),
            'code' => 'RNA'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'renew',
            'discount_value' => 5,
            'min_amount' => 0,
            'status' => 1,
        ], ['operator' => 'tester']);
        $couponB = app(CouponService::class)->createCoupon([
            'name' => '续费减三'.uniqid(),
            'code' => 'RNB'.uniqid(),
            'distribution_type' => 'private',
            'user_ids' => [(int) $user->id],
            'discount_type' => 'fixed',
            'discount_scope' => 'renew',
            'discount_value' => 3,
            'min_amount' => 0,
            'status' => 1,
        ], ['operator' => 'tester']);

        return [$user, $service, $couponA, $couponB];
    }

    private function userCouponOf(User $user, array $coupon): UserCoupon
    {
        return UserCoupon::query()
            ->where('user_id', (int) $user->id)
            ->where('coupon_id', (int) $coupon['id'])
            ->firstOrFail();
    }

    /**
     * 直接为服务落下持续优惠绑定快照（模拟新购 recurring 券开通后的绑定产物）。
     */
    private function bindRenewCoupon(Service $service, array $coupon, string $discountType, float $discountValue): void
    {
        $couponRow = Coupon::query()->findOrFail((int) $coupon['id']);
        $service->forceFill([
            'renew_coupon_id' => (int) $coupon['id'],
            'renew_coupon_snapshot' => [
                'coupon_id' => (int) $coupon['id'],
                'coupon_code' => (string) $couponRow->code,
                'coupon_name' => (string) $coupon['name'],
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'max_discount_amount' => null,
                'discount_label' => $discountType === 'percentage' ? '9 折优惠' : '立减 ¥5',
                'bound_invoice_no' => 'BIND-'.uniqid(),
                'bound_at' => now()->format('Y-m-d H:i:s'),
            ],
        ])->save();
        $service->refresh();
    }
}
