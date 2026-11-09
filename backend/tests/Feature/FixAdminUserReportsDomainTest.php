<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAccount;
use App\Services\Finance\AdminFinanceQueryService;
use App\Services\User\UserService;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 管理端用户/报表域财务审查修复回归（admin-user-reports 文件域）：
 * - M-95：finance.hot_standby=false 时 User::balance 必须读内核余额，不得读停更的热备列；
 * - M-97：用户详情 unpaid_amount 需扣减部分核销的 paid_amount；
 * - M-98：日报统计跨度上限 366 天；
 * - M-99：「已支付」按 orders.paid_at 归日，改 updated_at 后历史日报不漂移；
 * - M-77：「充值记录」只含充值口径支付，订单账单支付不得混入。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixAdminUserReportsDomainTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->makeAdmin());
    }

    public function test_balance_reads_kernel_when_hot_standby_disabled(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 120.00);

        // 热备列仍在（列删除前窗口），但已被手工写旧值；开关关闭时不得再信它。
        // 走查询构造器更新以绕过 UserAccount 的 version 守卫（测试造数专用）。
        UserAccount::query()->where('user_id', $user->id)->update(['cash_balance' => '1.00']);

        config(['finance.hot_standby' => true]);
        $withStandby = User::query()->findOrFail((int) $user->id)->load('account');
        $this->assertSame('1.00', $withStandby->balance);

        config(['finance.hot_standby' => false]);
        $withoutStandby = User::query()->findOrFail((int) $user->id)->load('account');
        $this->assertSame('120.00', $withoutStandby->balance);
    }

    public function test_user_detail_unpaid_amount_deducts_partially_settled_paid_amount(): void
    {
        $user = $this->makeUser();

        // 部分核销：应收 100，已入账 30，未付应为 70。
        Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'type' => InvoiceType::NEW_PURCHASE,
            'amount' => 100.00,
            'paid_amount' => 30.00,
            'status' => InvoiceStatus::UNPAID,
        ]);
        // 全未付对照：应收 50，未付 50。
        Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'type' => InvoiceType::NEW_PURCHASE,
            'amount' => 50.00,
            'paid_amount' => 0.00,
            'status' => InvoiceStatus::UNPAID,
        ]);

        $detail = app(UserService::class)->detail(User::query()->findOrFail((int) $user->id));

        $this->assertSame(120.0, (float) $detail['stats']['unpaid_amount']);
    }

    public function test_daily_summary_rejects_span_over_366_days(): void
    {
        $service = app(AdminFinanceQueryService::class);

        try {
            $service->dailyCustomerSummary('2024-01-01', '2025-01-02');
            $this->fail('跨度超过 366 天必须被拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame('统计跨度不能超过 366 天', $exception->getMessage());
        }

        // 边界内（整年 365 天）必须放行。
        $summary = $service->dailyCustomerSummary('2025-01-01', '2026-01-01');
        $this->assertSame('2025-01-01', $summary['start_date']);
        $this->assertSame('2026-01-01', $summary['end_date']);
    }

    public function test_daily_summary_counts_paid_orders_by_paid_at_not_updated_at(): void
    {
        $user = $this->makeUser();
        $paidDay = '2025-03-10';
        $driftedDayDate = '2025-03-20';

        // 共用 idc_test 库存在既有数据：断言相对基线的增量，而不是绝对值。
        $service = app(AdminFinanceQueryService::class);
        $paidDayBaseline = $service->dailyCustomerSummary($paidDay, $paidDay);
        $driftedDayBaseline = $service->dailyCustomerSummary($driftedDayDate, $driftedDayDate);

        $order = $this->makeOrder($user, OrderStatus::PAID, $paidDay.' 10:00:00');
        // 支付后被后续操作重写 updated_at（部分退款/对账回填等场景），不得把订单挪日。
        $order->forceFill(['updated_at' => $driftedDayDate.' 09:00:00'])->save();

        $summary = $service->dailyCustomerSummary($paidDay, $paidDay);
        $this->assertSame(
            (int) $paidDayBaseline['summary']['completed_orders'] + 1,
            (int) $summary['summary']['completed_orders']
        );

        // updated_at 所在的 2025-03-20 不得出现该订单
        $driftedDay = $service->dailyCustomerSummary($driftedDayDate, $driftedDayDate);
        $this->assertSame(
            (int) $driftedDayBaseline['summary']['completed_orders'],
            (int) $driftedDay['summary']['completed_orders']
        );
    }

    public function test_recharge_list_excludes_order_invoice_payments(): void
    {
        $user = $this->makeUser();
        $prefix = 'FIX-RC-'.uniqid().'-';

        // 充值口径：充值账单支付 + 未挂账单的直达充值单
        $this->makePayment($user, PaymentStatus::SUCCESS, $prefix.'A', InvoiceType::RECHARGE);
        $this->makePayment($user, PaymentStatus::PENDING, $prefix.'B', null);
        // 非充值口径：新购/续费账单支付不得出现在充值列表
        $this->makePayment($user, PaymentStatus::SUCCESS, $prefix.'C', InvoiceType::NEW_PURCHASE);
        $this->makePayment($user, PaymentStatus::SUCCESS, $prefix.'D', InvoiceType::RENEW);

        $paginator = app(AdminFinanceQueryService::class)->paginateRecharges(['keyword' => $prefix]);

        $this->assertSame(2, $paginator->total());
        $paymentNos = $paginator->getCollection()->pluck('payment_no')->all();
        $this->assertContains($prefix.'A', $paymentNos);
        $this->assertContains($prefix.'B', $paymentNos);
    }

    private function makeOrder(User $user, int $status, string $paidAt): Order
    {
        return Order::query()->create([
            'order_no' => 'FX'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => $status === OrderStatus::PENDING ? 0 : 100.00,
            'status' => $status,
            'paid_at' => $status === OrderStatus::PENDING ? null : $paidAt,
        ]);
    }

    private function makePayment(User $user, int $status, string $paymentNo, ?string $invoiceType): Payment
    {
        $invoiceId = null;

        if ($invoiceType !== null) {
            $invoice = Invoice::query()->create([
                'invoice_no' => Invoice::generateInvoiceNo(),
                'user_id' => $user->id,
                'type' => $invoiceType,
                'amount' => 50.00,
                'paid_amount' => $status === PaymentStatus::SUCCESS ? 50.00 : 0.00,
                'status' => $status === PaymentStatus::SUCCESS ? InvoiceStatus::PAID : InvoiceStatus::UNPAID,
                'paid_at' => $status === PaymentStatus::SUCCESS ? now() : null,
            ]);
            $invoiceId = (int) $invoice->id;
        }

        return Payment::query()->create([
            'payment_no' => $paymentNo,
            'user_id' => $user->id,
            'invoice_id' => $invoiceId,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 50.00,
            'currency' => 'CNY',
            'status' => $status,
            'paid_at' => $status === PaymentStatus::PENDING ? null : now(),
        ]);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'admin-user-reports-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'admin-user-reports-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeAdmin(): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '用户报表域测试角色',
            'permissions' => [
                AdminPermissions::USER_DETAIL,
                AdminPermissions::USER_MANAGE,
                AdminPermissions::FINANCE_REPORT,
            ],
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '用户报表域测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
