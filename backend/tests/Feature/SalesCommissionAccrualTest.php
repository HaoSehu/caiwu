<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ReferralReward;
use App\Models\Role;
use App\Models\SalesCommission;
use App\Models\SalesCommissionPlan;
use App\Models\SalesCommissionProductRate;
use App\Models\SalesCommissionTier;
use App\Models\User;
use App\Services\SalesCommission\SalesCommissionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M5 内部销售提成计提行为快照。
 *
 * 核心不变式：
 * - 与推广大使返利并存双算：计提不读写 referral_rewards，互不影响；
 * - 费率优先级：产品费率（fixed_amount > rate）> 方案阶梯 > 无（不计提）；
 * - 阶梯边界：min ≤ base < max（max NULL=∞）；
 * - 幂等：invoice_id 唯一，重复计提 skip；
 * - 无归属 / 无命中方案（过期）不计提。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class SalesCommissionAccrualTest extends TestCase
{
    use DatabaseTransactions;

    private SalesCommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SalesCommissionService::class);
    }

    public function test_accrual_does_not_touch_referral_rewards(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $invoice = $this->makePaidInvoice($user, 200.00);
        $plan = $this->makePlan();
        SalesCommissionTier::query()->create(['plan_id' => $plan->id, 'min_amount' => 0, 'max_amount' => null, 'rate' => 5.00]);

        // 既有推广返利（冻结态）挂同一账单
        $reward = ReferralReward::query()->create([
            'referrer_user_id' => $this->makeUser('referrer')->id,
            'referred_user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'order_amount' => 200.00,
            'reward_rate' => 10.00,
            'reward_amount' => 20.00,
            'status' => ReferralReward::STATUS_FROZEN,
        ]);

        $commission = $this->service->accrueForInvoice($invoice, 'payment');

        $this->assertNotNull($commission);
        $this->assertSame('10.00', (string) $commission->commission_amount);

        // 并存双算：计提后返利记录零变化
        $this->assertSame(1, ReferralReward::query()->where('invoice_id', (int) $invoice->id)->count());
        $reward->refresh();
        $this->assertSame(ReferralReward::STATUS_FROZEN, (int) $reward->status);
    }

    public function test_referral_reward_path_does_not_touch_sales_commissions(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $invoice = $this->makePaidInvoice($user, 200.00);
        $plan = $this->makePlan();
        SalesCommissionTier::query()->create(['plan_id' => $plan->id, 'min_amount' => 0, 'max_amount' => null, 'rate' => 5.00]);
        $this->service->accrueForInvoice($invoice, 'payment');

        // 反向：账单推广返利路径不产生/改动提成记录（双算解耦）
        $this->assertSame(1, SalesCommission::query()->where('invoice_id', (int) $invoice->id)->count());
        $this->assertSame(0, ReferralReward::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_product_rate_overrides_tier(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $invoice = $this->makePaidInvoice($user, 200.00);
        $plan = $this->makePlan();
        SalesCommissionTier::query()->create(['plan_id' => $plan->id, 'min_amount' => 0, 'max_amount' => null, 'rate' => 5.00]);
        $product = $invoice->product_id !== null ? $invoice->product_id : $this->makeProduct();
        $invoice->forceFill(['product_id' => $product])->save();
        SalesCommissionProductRate::query()->create(['plan_id' => $plan->id, 'product_id' => $product, 'rate' => 20.00]);

        $commission = $this->service->accrueForInvoice($invoice, 'payment');

        $this->assertNotNull($commission);
        $this->assertSame('40.00', (string) $commission->commission_amount);
        $this->assertSame('20.00', (string) $commission->rate);
        $this->assertNull($commission->plan_snapshot['tier_id'] ?? null);
    }

    public function test_fixed_amount_takes_priority_over_product_rate(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $invoice = $this->makePaidInvoice($user, 200.00);
        $plan = $this->makePlan();
        $product = $this->makeProduct();
        $invoice->forceFill(['product_id' => $product])->save();
        SalesCommissionProductRate::query()->create([
            'plan_id' => $plan->id,
            'product_id' => $product,
            'rate' => 20.00,
            'fixed_amount' => 50.00,
        ]);

        $commission = $this->service->accrueForInvoice($invoice, 'payment');

        $this->assertNotNull($commission);
        $this->assertSame('50.00', (string) $commission->commission_amount);
        $this->assertSame(50.0, (float) ($commission->plan_snapshot['fixed_amount'] ?? 0));
    }

    public function test_tier_boundary_min_inclusive_max_exclusive(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $plan = $this->makePlan();
        SalesCommissionTier::query()->create(['plan_id' => $plan->id, 'min_amount' => 0, 'max_amount' => 100, 'rate' => 5.00]);
        SalesCommissionTier::query()->create(['plan_id' => $plan->id, 'min_amount' => 100, 'max_amount' => 200, 'rate' => 10.00]);

        // 99.99 → 第一档 5%
        $lowInvoice = $this->makePaidInvoice($user, 99.99);
        $lowCommission = $this->service->accrueForInvoice($lowInvoice, 'payment');
        $this->assertNotNull($lowCommission);
        $this->assertSame('5.00', (string) $lowCommission->rate);
        $this->assertSame('5.00', (string) $lowCommission->commission_amount);

        // 100.00 → 第二档 10%（min 含）
        $midInvoice = $this->makePaidInvoice($user, 100.00);
        $midCommission = $this->service->accrueForInvoice($midInvoice, 'payment');
        $this->assertNotNull($midCommission);
        $this->assertSame('10.00', (string) $midCommission->rate);
        $this->assertSame('10.00', (string) $midCommission->commission_amount);

        // 200.00 → 超出所有阶梯（max 不含），不计提
        $highInvoice = $this->makePaidInvoice($user, 200.00);
        $this->assertNull($this->service->accrueForInvoice($highInvoice, 'payment'));
        $this->assertSame(0, SalesCommission::query()->where('invoice_id', (int) $highInvoice->id)->count());
    }

    public function test_accrual_is_idempotent_per_invoice(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $invoice = $this->makePaidInvoice($user, 200.00);
        $plan = $this->makePlan();
        SalesCommissionTier::query()->create(['plan_id' => $plan->id, 'min_amount' => 0, 'max_amount' => null, 'rate' => 5.00]);

        $first = $this->service->accrueForInvoice($invoice, 'payment');
        $second = $this->service->accrueForInvoice($invoice, 'payment');

        $this->assertNotNull($first);
        $this->assertSame((int) $first->id, (int) $second?->id);
        $this->assertSame(1, SalesCommission::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_no_sales_owner_no_accrual(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makePaidInvoice($user, 200.00);
        $this->makePlan();

        $this->assertNull($this->service->accrueForInvoice($invoice, 'payment'));
        $this->assertSame(0, SalesCommission::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_expired_plan_no_accrual(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        $invoice = $this->makePaidInvoice($user, 200.00);
        $this->makePlan(['valid_from' => now()->subDays(30)->toDateString(), 'valid_to' => now()->subDay()->toDateString()]);

        $this->assertNull($this->service->accrueForInvoice($invoice, 'payment'));
        $this->assertSame(0, SalesCommission::query()->where('invoice_id', (int) $invoice->id)->count());
    }

    public function test_basis_order_amount_uses_invoice_amount(): void
    {
        $user = $this->makeUser();
        $sales = $this->makeAdmin();
        $user->forceFill(['sales_owner_admin_id' => $sales->id])->save();

        // 实付 90（折扣后），订单金额（账单应收）100：basis=2 应以 100 为基数
        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 90.00,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        $plan = $this->makePlan(['basis' => SalesCommissionPlan::BASIS_ORDER_AMOUNT]);
        SalesCommissionTier::query()->create(['plan_id' => $plan->id, 'min_amount' => 0, 'max_amount' => null, 'rate' => 10.00]);

        $commission = $this->service->accrueForInvoice($invoice, 'payment');

        $this->assertNotNull($commission);
        $this->assertSame('100.00', (string) $commission->base_amount);
        $this->assertSame('10.00', (string) $commission->commission_amount);
    }

    public function test_resolve_rate_direct_call(): void
    {
        $plan = $this->makePlan();
        SalesCommissionTier::query()->create(['plan_id' => $plan->id, 'min_amount' => 0, 'max_amount' => 100, 'rate' => 5.00]);
        $product = $this->makeProduct();
        SalesCommissionProductRate::query()->create(['plan_id' => $plan->id, 'product_id' => $product, 'fixed_amount' => 30.00]);

        // 产品 fixed_amount 优先
        $fixed = $this->service->resolveRate(['id' => $plan->id], $product, 200.00);
        $this->assertSame(30.0, $fixed['fixed_amount']);
        $this->assertNull($fixed['tier_id']);

        // 无产品命中走阶梯
        $tier = $this->service->resolveRate(['id' => $plan->id], null, 50.00);
        $this->assertSame(5.0, $tier['rate']);

        // 超出阶梯返回 null
        $this->assertNull($this->service->resolveRate(['id' => $plan->id], null, 100.00));
    }

    private function makePlan(array $overrides = []): SalesCommissionPlan
    {
        return SalesCommissionPlan::query()->create(array_merge([
            'name' => '默认提成方案-'.uniqid(),
            'basis' => SalesCommissionPlan::BASIS_PAID_AMOUNT,
            'is_enabled' => 1,
        ], $overrides));
    }

    private function makeUser(string $prefix = 'm5'): User
    {
        return User::query()->create([
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeAdmin(string $prefix = 'm5sales'): AdminUser
    {
        // admin_users.role_id NOT NULL 且 FK→roles，必须先造角色父行。
        $role = Role::query()->create([
            'name' => $prefix.'-role-'.uniqid(),
            'permissions' => [],
        ]);

        return AdminUser::query()->create([
            'username' => $prefix.'-'.uniqid(),
            'password' => 'x',
            'role_id' => (int) $role->id,
            'status' => 1,
        ]);
    }

    private function makeProduct(): int
    {
        return (int) Product::query()->create([
            'name' => 'M5 测试商品-'.uniqid(),
            'product_type' => 'cloud_host',
            'service_type_code' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '10.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ])->id;
    }

    private function makePaidInvoice(User $user, float $amount): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => $amount,
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);
    }
}
