<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\ProductType;
use App\Constants\UserCouponStatus;
use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Coupon;
use App\Models\FirstProductGroup;
use App\Models\Invoice;
use App\Models\MarketingProductGroup;
use App\Models\MarketingProductGroupItem;
use App\Models\MemberLevel;
use App\Models\MemberLevelGroupDiscount;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SecondProductGroup;
use App\Models\ThirdProductGroup;
use App\Models\User;
use App\Models\UserAccount;
use App\Models\UserCoupon;
use App\Services\Finance\CouponService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 模拟用户用券的完整链路（真实写库）：领券 → 报价 → 下单 → 余额支付 → 核销。
 *
 * 两个视角：
 * 1) 展示：折扣（券减免/会员折扣分列）与优惠券（券码/券名）必须在三端订单/账单的
 *    列表与详情都能看到——其中管理端订单列表**默认 tab**（OrderV2QueryService +
 *    AdminOrderSummaryResource）与订单详情（findAdminOrder 显式 select）是此前漏改的两条路径。
 * 2) 使用：支付后券必须被核销（user_coupon 转已使用、coupon.used_count +1、清预留），
 *    且同一张券不得再次使用；持有券分组转为「已用完」。
 *
 * 口径：amount=应付价(已扣两类折扣)；discount=优惠券减免；member_discount_amount=会员折扣。
 * 目录价 100、会员 9 折(减 10)、券 8 折(对 90 再减 18) → 应付 72。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class CouponDiscountDisplayEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    private const CATALOG_AMOUNT = '100.00';

    private const MEMBER_DISCOUNT = '10.00';

    private const COUPON_DISCOUNT = '18.00';

    private const PAYABLE_AMOUNT = '72.00';

    public function test_simulated_user_purchase_displays_discount_and_coupon_on_all_surfaces(): void
    {
        [$user, $product, $coupon, $userCouponId, $invoice, $order, $quote] = $this->purchaseWithCoupon();

        // 报价契约：券减免在 discount_amount，会员折扣包在 member_discount.amount（为 0 时为 null）
        $this->assertSame(self::MEMBER_DISCOUNT, (string) ($quote['member_discount']['amount'] ?? ''), '报价未体现会员折扣');
        $this->assertSame(self::COUPON_DISCOUNT, (string) $quote['discount_amount']);
        $this->assertSame(self::PAYABLE_AMOUNT, (string) $quote['total_amount']);

        // 落库口径：两类折扣分列，且 amount 是应付价（三者相加还原目录价）
        $this->assertSame(self::PAYABLE_AMOUNT, number_format((float) $invoice->amount, 2, '.', ''));
        $this->assertSame(self::COUPON_DISCOUNT, number_format((float) $invoice->discount, 2, '.', ''));
        $this->assertSame(self::MEMBER_DISCOUNT, number_format((float) $invoice->member_discount_amount, 2, '.', ''));
        $this->assertSame((string) $coupon['code'], (string) $invoice->coupon_code);
        $this->assertSame(
            self::CATALOG_AMOUNT,
            number_format(
                (float) $invoice->amount + (float) $invoice->discount + (float) $invoice->member_discount_amount,
                2,
                '.',
                ''
            ),
            '应付价 + 券减免 + 会员折扣 必须还原目录价'
        );

        $this->assertSame(self::PAYABLE_AMOUNT, number_format((float) $order->amount, 2, '.', ''));
        $this->assertSame(self::COUPON_DISCOUNT, number_format((float) $order->discount, 2, '.', ''));
        $this->assertSame(self::MEMBER_DISCOUNT, number_format((float) $order->member_discount_amount, 2, '.', ''));
        $this->assertSame((string) $coupon['code'], (string) $order->coupon_code);

        // 控制台读接口（待支付状态）
        $this->assertClientSurfaces($order->id, $invoice->id, $coupon);

        // 真实支付（余额）→ 账单转已支付；核销走 coupon 队列，此处按真实状态展示
        $this->payByBalance($user, $invoice);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame(self::PAYABLE_AMOUNT, number_format((float) $invoice->paid_amount, 2, '.', ''));

        // 核销同步未回写时，持有券展示也必须给「已使用」而不是「可使用」
        $ownedCoupon = collect($this->getJson('/api/v2/client/coupons?page=1&page_size=50')
            ->assertOk()
            ->json('data.list'))
            ->firstWhere('id', $userCouponId);
        $this->assertIsArray($ownedCoupon, '持有券列表里找不到刚核销的券');
        $this->assertSame('used_up', $ownedCoupon['status'] ?? null, '已支付账单的券必须展示为已用完/已使用');
        $this->assertSame('已使用', $ownedCoupon['status_label'] ?? null);

        // 管理端读接口（已支付状态）
        Sanctum::actingAs($this->makeAdmin());
        $this->assertAdminSurfaces($order->id, $invoice->id, $coupon);
    }

    public function test_coupon_is_consumed_after_payment_and_cannot_be_reused(): void
    {
        [$user, $product, $coupon, $userCouponId, $invoice] = $this->purchaseWithCoupon();

        $this->payByBalance($user, $invoice);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);

        // 支付成功后应向 coupon 队列投递核销任务（QUEUE_CONNECTION=database，真实落 jobs 表）
        $this->assertTrue(
            DB::table('jobs')->where('queue', 'coupon')->exists(),
            '支付后应向 coupon 队列投递核销任务'
        );

        // 跑核销（队列任务内部调用的同一入口）
        app(CouponService::class)->syncInvoiceCouponUsage($invoice->fresh());

        $userCoupon = UserCoupon::query()->findOrFail($userCouponId);
        $this->assertSame(UserCouponStatus::USED, (int) $userCoupon->status, '核销后持有券应为已使用');
        $this->assertNotNull($userCoupon->used_at, '核销后应写入 used_at');
        $this->assertNull($userCoupon->reserved_until, '核销后必须清预留');

        $this->assertSame(
            1,
            (int) Coupon::query()->findOrFail((int) $coupon['id'])->used_count,
            '券模板使用次数应 +1'
        );

        // 券不再可用：结账可用券列表为空
        Sanctum::actingAs($user);
        $this->assertSame(
            [],
            app(CouponService::class)->availableCouponsForCheckout((int) $user->id, $product, 'monthly', 90.0, 'new'),
            '已核销的券不得再出现在结账可用列表'
        );

        // 再次占用同一张券 → 被拒
        $message = null;
        try {
            app(CouponService::class)->reserveOwnedCouponForInvoice(
                $userCouponId,
                (int) $user->id,
                $product,
                'monthly',
                90.0,
                'new'
            );
        } catch (BusinessException $exception) {
            $message = $exception->getMessage();
        }
        $this->assertNotNull($message, '已核销的券不得再次占用');
        $this->assertStringContainsString('优惠券', (string) $message);

        // 持有券分组：可用 0、已用完 1
        $summary = app(CouponService::class)->summaryForUser($user);
        $this->assertSame(0, $summary['available'], '核销后可用分组应为 0');
        $this->assertSame(1, $summary['used_up'], '核销后应落入已用完分组');

        // 管理端券列表：已使用 1 次
        Sanctum::actingAs($this->makeAdmin(['product.manage']));
        $row = collect($this->getJson('/api/v2/admin/coupons?page=1&page_size=50')
            ->assertOk()
            ->json('data.list'))
            ->firstWhere('id', (int) $coupon['id']);
        $this->assertIsArray($row, '管理端券列表里找不到该券');
        $this->assertSame(1, (int) ($row['used_count'] ?? -1), '管理端券列表应显示已使用 1 次');
    }

    /**
     * 模拟用户用券购买：领券 → 报价 → 下单（真实写库）。
     *
     * @return array{0: User, 1: Product, 2: array<string, mixed>, 3: int, 4: Invoice, 5: Order, 6: array<string, mixed>}
     */
    private function purchaseWithCoupon(): array
    {
        [$user, $product, $coupon] = $this->fixture();
        Sanctum::actingAs($user);

        // 1) 用户领取公开券（真实写 user_coupons）
        $userCouponId = (int) $this->postJson("/api/v2/client/coupons/{$coupon['id']}/claim")
            ->assertOk()
            ->json('data.id');
        $this->assertGreaterThan(0, $userCouponId);

        // 2) 前台报价
        $quote = $this->postJson("/api/v2/site/products/{$product->id}/quote", [
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'user_coupon_id' => $userCouponId,
        ])->assertOk()->json('data');

        // 3) 下单（真实写 invoices/orders）
        $this->postJson('/api/v2/client/invoices', [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'quantity' => 1,
            'config' => [],
            'quote_token' => (string) $quote['quote_token'],
            'user_coupon_id' => $userCouponId,
        ], ['X-Idempotency-Key' => 'e2e-'.uniqid()])->assertOk();

        $invoice = Invoice::query()->where('user_id', (int) $user->id)->latest('id')->firstOrFail();

        return [$user, $product, $coupon, $userCouponId, $invoice, $invoice->order()->firstOrFail(), $quote];
    }

    private function payByBalance(User $user, Invoice $invoice): void
    {
        Sanctum::actingAs($user);
        $sessionToken = (string) $this->getJson("/api/v2/client/invoices/{$invoice->id}")
            ->assertOk()
            ->json('data.invoice.payment_options.payment_security.session_token');
        $this->assertNotSame('', $sessionToken, '账单详情未下发支付会话令牌');

        // 夹具商品无供应商绑定，开通履约会返回「履约未完成」；此处只关心资金与券的落库口径
        $response = $this->postJson("/api/v2/client/invoices/{$invoice->id}/pay/balance", [
            'payment_session_token' => $sessionToken,
        ]);
        $this->assertNotSame(500, $response->getStatusCode(), '余额支付不应出现未捕获异常');
    }

    private function assertClientSurfaces(int $orderId, int $invoiceId, array $coupon): void
    {
        $orderRow = $this->getJson('/api/v2/client/orders?page=1&page_size=20')
            ->assertOk()
            ->json('data.list');
        $this->assertSame(self::COUPON_DISCOUNT, $this->rowOf($orderRow, $orderId)['discount'] ?? null, '控制台订单列表缺券减免');
        $this->assertSame(self::MEMBER_DISCOUNT, $this->rowOf($orderRow, $orderId)['member_discount_amount'] ?? null, '控制台订单列表缺会员折扣');
        $this->assertSame((string) $coupon['code'], $this->rowOf($orderRow, $orderId)['coupon_code'] ?? null, '控制台订单列表缺券码');

        $orderDetail = $this->getJson("/api/v2/client/orders/{$orderId}")->assertOk()->json('data');
        $this->assertSame(self::COUPON_DISCOUNT, $orderDetail['discount'] ?? null, '控制台订单详情缺券减免');
        $this->assertSame(self::MEMBER_DISCOUNT, $orderDetail['member_discount_amount'] ?? null, '控制台订单详情缺会员折扣');
        $this->assertSame((string) $coupon['name'], $orderDetail['coupon_name'] ?? null, '控制台订单详情缺券名');

        $invoiceRow = $this->getJson('/api/v2/client/invoices?page=1&page_size=20')
            ->assertOk()
            ->json('data.list');
        $this->assertSame(self::COUPON_DISCOUNT, $this->rowOf($invoiceRow, $invoiceId)['discount'] ?? null, '控制台账单列表缺券减免');
        $this->assertSame(self::MEMBER_DISCOUNT, $this->rowOf($invoiceRow, $invoiceId)['member_discount_amount'] ?? null, '控制台账单列表缺会员折扣');
        $this->assertSame((string) $coupon['name'], $this->rowOf($invoiceRow, $invoiceId)['coupon_name'] ?? null, '控制台账单列表缺券名');

        $invoiceDetail = $this->getJson("/api/v2/client/invoices/{$invoiceId}")->assertOk()->json('data.invoice');
        $this->assertSame(self::COUPON_DISCOUNT, $invoiceDetail['financial']['discount'] ?? null, '控制台账单详情缺券减免');
        $this->assertSame(self::MEMBER_DISCOUNT, $invoiceDetail['display']['member_discount_amount'] ?? null, '控制台账单详情缺会员折扣');
        $this->assertSame((string) $coupon['code'], $invoiceDetail['display']['coupon_code'] ?? null, '控制台账单详情缺券码');
        $this->assertSame((string) $coupon['name'], $invoiceDetail['display']['coupon_name'] ?? null, '控制台账单详情缺券名');
    }

    private function assertAdminSurfaces(int $orderId, int $invoiceId, array $coupon): void
    {
        // 默认 tab：/v2/admin/orders → OrderV2QueryService + AdminOrderSummaryResource
        $orderRow = $this->getJson('/api/v2/admin/orders?page=1&page_size=20')
            ->assertOk()
            ->json('data.list');
        $this->assertSame(self::COUPON_DISCOUNT, $this->rowOf($orderRow, $orderId)['discount'] ?? null, '管理端订单列表缺券减免');
        $this->assertSame(self::MEMBER_DISCOUNT, $this->rowOf($orderRow, $orderId)['member_discount_amount'] ?? null, '管理端订单列表缺会员折扣');
        $this->assertSame((string) $coupon['code'], $this->rowOf($orderRow, $orderId)['coupon_code'] ?? null, '管理端订单列表缺券码');

        // 订单详情：findAdminOrder 显式 select 必须含 member_discount_amount
        $orderDetail = $this->getJson("/api/v2/admin/orders/{$orderId}")->assertOk()->json('data.order');
        $this->assertSame(self::COUPON_DISCOUNT, $orderDetail['financial']['discount'] ?? null, '管理端订单详情缺券减免');
        $this->assertSame(self::MEMBER_DISCOUNT, $orderDetail['financial']['member_discount_amount'] ?? null, '管理端订单详情缺会员折扣');
        $this->assertSame((string) $coupon['code'], $orderDetail['coupon']['code'] ?? null, '管理端订单详情缺券码');

        $invoiceRow = $this->getJson('/api/v2/admin/invoices?page=1&page_size=20')
            ->assertOk()
            ->json('data.list');
        $this->assertSame(self::COUPON_DISCOUNT, $this->rowOf($invoiceRow, $invoiceId)['discount'] ?? null, '管理端账单列表缺券减免');
        $this->assertSame(self::MEMBER_DISCOUNT, $this->rowOf($invoiceRow, $invoiceId)['member_discount_amount'] ?? null, '管理端账单列表缺会员折扣');
        $this->assertSame((string) $coupon['name'], $this->rowOf($invoiceRow, $invoiceId)['coupon_name'] ?? null, '管理端账单列表缺券名');

        $invoiceDetail = $this->getJson("/api/v2/admin/invoices/{$invoiceId}")->assertOk()->json('data.invoice');
        $this->assertSame(self::COUPON_DISCOUNT, $invoiceDetail['financial']['discount'] ?? null, '管理端账单详情缺券减免');
        $this->assertSame(self::MEMBER_DISCOUNT, $invoiceDetail['financial']['member_discount_amount'] ?? null, '管理端账单详情缺会员折扣');
        $this->assertSame((string) $coupon['name'], $invoiceDetail['financial']['coupon_name'] ?? null, '管理端账单详情缺券名');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function rowOf(array $rows, int $id): array
    {
        foreach ($rows as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }

        $this->fail("列表响应中找不到记录 #{$id}");
    }

    /**
     * @return array{0: User, 1: Product, 2: array<string, mixed>}
     */
    private function fixture(): array
    {
        $product = $this->makeVisibleProduct();

        $level = MemberLevel::query()->create(['name' => 'E2E等级'.uniqid(), 'status' => 1]);
        $group = MarketingProductGroup::query()->create(['name' => 'E2E营销组'.uniqid(), 'sort_order' => 0]);
        MemberLevelGroupDiscount::query()->create([
            'member_level_id' => (int) $level->id,
            'marketing_product_group_id' => (int) $group->id,
            'discount_type' => MemberLevelGroupDiscount::TYPE_PERCENT,
            'discount_value' => 90, // 9 折 → 目录价 100 减 10
        ]);
        MarketingProductGroupItem::query()->create([
            'marketing_product_group_id' => (int) $group->id,
            'product_id' => (int) $product->id,
        ]);

        $user = User::factory()->create([
            'is_verified' => 1,
            'member_level_id' => (int) $level->id,
        ]);
        // 余额账户：供余额支付
        UserAccount::query()->create([
            'user_id' => (int) $user->id,
            'cash_balance' => '200.00',
        ]);

        $created = app(CouponService::class)->createCoupon([
            'name' => 'E2E八折券'.uniqid(),
            'code' => 'E2E'.strtoupper(uniqid()),
            'distribution_type' => 'public',
            'discount_type' => 'percentage',
            'discount_value' => 80, // 折后价比例 80 → 实付 80%（8 折）
            'discount_scope' => 'first_month',
            'min_amount' => 0,
            'status' => 1,
        ], ['operator' => 'e2e']);

        // 管理端契约不外露券码（券码为内部标识），断言用值从模型取
        $couponModel = Coupon::query()->findOrFail((int) $created['id']);

        return [
            $user,
            $product,
            [
                'id' => (int) $couponModel->id,
                'code' => (string) $couponModel->code,
                'name' => (string) $couponModel->name,
            ],
        ];
    }

    private function makeVisibleProduct(): Product
    {
        $first = FirstProductGroup::query()->firstOrCreate(
            ['code' => ProductType::VPS],
            [
                'product_type' => 'cloud_server',
                'name' => '云服务器',
                'slug' => 'e2e-display-first-'.uniqid(),
                'sort_order' => 999,
                'is_visible' => 1,
                'is_system' => 0,
            ]
        );
        if ((int) $first->is_visible !== 1) {
            $first->forceFill(['is_visible' => 1])->save();
        }

        $second = SecondProductGroup::query()->create([
            'first_product_group_id' => (int) $first->id,
            'name' => 'E2E二级分组',
            'slug' => 'e2e-display-second-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);
        $third = ThirdProductGroup::query()->create([
            'second_product_group_id' => (int) $second->id,
            'name' => 'E2E三级分组',
            'slug' => 'e2e-display-third-'.uniqid(),
            'sort_order' => 999,
            'is_visible' => 1,
        ]);

        return Product::query()->create([
            'product_type' => ProductType::VPS,
            'name' => 'E2E商品'.uniqid(),
            'product_group_id' => (int) $third->id,
            'status' => 1,
            'stock' => 10,
            'pricing' => ['monthly' => self::CATALOG_AMOUNT],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'auto_setup' => 0,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function makeAdmin(array $permissions = ['order.list', 'order.detail', 'invoice.list', 'invoice.detail']): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => 'E2E财务只读角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => 'E2E管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
