<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Finance\AdminFinanceQueryService;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\ClientInvoicePaymentWorkflowService;
use App\Services\Finance\InvoiceV2QueryService;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 账单/支付状态已收敛为与订单一致的 4 态（0待支付/1已支付/4已取消/5已退款）：
 * 已逾期/部分退款/失败状态不再存在，常量 4 键、各状态筛选均精确匹配，
 * 客户端账单 summary 不再含 overdue 桶。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class InvoicePaymentStatusFilterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invoice_status_constants_are_four_states(): void
    {
        $this->assertSame([0, 1, 4, 5], array_keys(InvoiceStatus::$labels));
        $this->assertSame(['待支付', '已支付', '已取消', '已退款'], array_values(InvoiceStatus::$labels));
    }

    public function test_payment_status_constants_are_four_states(): void
    {
        $expected = [
            PaymentStatus::PENDING => '待支付',
            PaymentStatus::SUCCESS => '已支付',
            PaymentStatus::CANCELLED => '已取消',
            PaymentStatus::REFUNDED => '已退款',
        ];

        $this->assertCount(4, PaymentStatus::$labels);
        $this->assertEqualsCanonicalizing($expected, PaymentStatus::$labels);
    }

    public function test_admin_invoice_filter_matches_exact_status(): void
    {
        $keyword = $this->makeInvoicesForFilter();

        $service = app(InvoiceV2QueryService::class);

        foreach (array_keys(InvoiceStatus::$labels) as $status) {
            $this->assertSame(1, $service->paginateAdminInvoices(['status' => $status, 'keyword' => $keyword])->total());
        }
    }

    public function test_client_invoice_filter_matches_exact_status(): void
    {
        $user = $this->makeUser('client');

        foreach (array_keys(InvoiceStatus::$labels) as $status) {
            $this->makeInvoice($user, $status);
        }

        $service = app(InvoiceV2QueryService::class);

        foreach (array_keys(InvoiceStatus::$labels) as $status) {
            $this->assertSame(1, $service->paginateClientInvoices((int) $user->id, ['status' => $status])->total());
        }
    }

    public function test_admin_recharge_filter_matches_exact_status(): void
    {
        $user = $this->makeUser('recharge');
        $prefix = 'PY'.uniqid().'-';

        foreach (array_keys(PaymentStatus::$labels) as $status) {
            $this->makePayment($user, $status, $prefix);
        }

        $service = app(AdminFinanceQueryService::class);

        foreach (array_keys(PaymentStatus::$labels) as $status) {
            $this->assertSame(1, $service->paginateRecharges(['status' => $status, 'keyword' => $prefix])->total());
        }
    }

    public function test_client_invoice_summary_has_no_overdue_bucket(): void
    {
        $user = $this->makeUser('summary');

        foreach (array_keys(InvoiceStatus::$labels) as $status) {
            $this->makeInvoice($user, $status);
        }

        $summary = app(ClientInvoicePaymentWorkflowService::class)->summary($user, []);

        $this->assertSame(1, $summary['unpaid']);
        $this->assertSame(1, $summary['paid']);
        $this->assertArrayHasKey('unpaid_amount', $summary);
        $this->assertArrayNotHasKey('overdue', $summary);
    }

    public function test_refund_guard_only_allows_paid_invoice(): void
    {
        $user = $this->makeUser('refund');
        $service = app(PaymentService::class);

        foreach ([InvoiceStatus::UNPAID, InvoiceStatus::CANCELLED, InvoiceStatus::REFUNDED] as $status) {
            $invoice = $this->makeInvoice($user, $status);

            try {
                $service->refundInvoiceToBalance($user, $invoice);
                $this->fail('Expected BusinessException for invoice status '.$status);
            } catch (BusinessException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cancel_guard_only_allows_unpaid_invoice(): void
    {
        $user = $this->makeUser('cancel');
        $service = app(CheckoutService::class);

        foreach ([InvoiceStatus::PAID, InvoiceStatus::CANCELLED, InvoiceStatus::REFUNDED] as $status) {
            $invoice = $this->makeInvoice($user, $status);

            try {
                $service->cancel($invoice);
                $this->fail('Expected BusinessException for invoice status '.$status);
            } catch (BusinessException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function makeInvoicesForFilter(): string
    {
        $user = $this->makeUser('filter');

        foreach (array_keys(InvoiceStatus::$labels) as $status) {
            $this->makeInvoice($user, $status);
        }

        // keyword 命中造数用户唯一邮箱，将管理端查询隔离到本测试数据
        return (string) $user->email;
    }

    public function test_user_invoice_type_filter_accepts_detail_page_options(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $user = $this->makeUser('type-filter');
        $refund = $this->makeInvoice($user, InvoiceStatus::PAID, InvoiceType::REFUND);

        // 用户详情页账单类型选项必须全部被请求校验放行，否则前端选中即 422。
        $options = [
            'new',
            'renew',
            InvoiceType::RECHARGE,
            InvoiceType::DEDUCTION,
            InvoiceType::UPGRADE,
            InvoiceType::REFUND,
            InvoiceType::REFERRAL_CREDIT,
            InvoiceType::MANUAL,
        ];

        foreach ($options as $type) {
            $this->getJson("/api/v2/admin/users/{$user->id}/invoices?type={$type}")->assertOk();
        }

        $this->getJson("/api/v2/admin/users/{$user->id}/invoices?type=".InvoiceType::REFUND)
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.invoice_no', (string) $refund->invoice_no);
    }

    private function makeAdmin(array $permissions = ['user.detail']): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '账单筛选测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '管理员',
            'status' => 1,
            'role_id' => $role->id,
        ]);
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeInvoice(User $user, int $status, string $type = 'new'): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => $type,
            'amount' => 100.00,
            'paid_amount' => $status === InvoiceStatus::UNPAID ? 0 : 100.00,
            'status' => $status,
            'paid_at' => $status === InvoiceStatus::UNPAID ? null : now(),
        ]);
    }

    private function makePayment(User $user, int $status, string $paymentNoPrefix = ''): Payment
    {
        return Payment::query()->create([
            'payment_no' => $paymentNoPrefix !== '' ? $paymentNoPrefix.$status : 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 100.00,
            'status' => $status,
            'paid_at' => $status === PaymentStatus::PENDING ? null : now(),
        ]);
    }
}
