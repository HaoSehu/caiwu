<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\ProductType;
use App\Constants\ServiceStatus;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ReferralWithdrawal;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理端仪表盘统计接口契约回归：
 * 1. 新增 todos（待办）与 services（服务与资源）分块，字段恒为整数；
 * 2. 新增 yesterday 环比口径，与 today 同源同量纲；
 * 3. 「7 天内到期」按「已开通 + 到期时间落在未来 7 天窗口内」统计，已过期与远期不计入。
 * 统计结果有 60 秒缓存，断言增量前先 flush，避免读到上一次请求的快照。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminDashboardStatsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs($this->makeAdmin());
    }

    public function test_stats_exposes_todo_service_and_yesterday_blocks(): void
    {
        $owner = $this->makeUser();

        $baseline = $this->fetchStats();

        $this->makeUser(['verification_status' => 4]);
        $this->makeInvoice($owner, InvoiceStatus::PAID, '100.00', now());
        $this->makeInvoice($owner, InvoiceStatus::UNPAID, '50.00');

        ReferralWithdrawal::query()->create([
            'user_id' => $owner->id,
            'amount' => '20.00',
            'method' => ReferralWithdrawal::METHOD_BALANCE,
            'status' => ReferralWithdrawal::STATUS_PENDING,
            'operator' => 'client',
            'trace_id' => 'dash-'.uniqid(),
        ]);

        $product = $this->makeProduct();
        $this->makeService($owner, $product, ServiceStatus::ACTIVE, now()->addDays(3));
        $this->makeService($owner, $product, ServiceStatus::ACTIVE, now()->addDays(30));

        $after = $this->fetchStats();

        $this->assertIsInt($after['todos']['unpaid_invoices']);
        $this->assertIsInt($after['todos']['open_tickets']);
        $this->assertIsInt($after['todos']['pending_verifications']);
        $this->assertIsInt($after['todos']['pending_withdrawals']);
        $this->assertIsInt($after['services']['total']);
        $this->assertIsInt($after['services']['expiring_soon']);
        $this->assertIsInt($after['counts']['active_services']);
        $this->assertIsNumeric($after['today']['income']);
        $this->assertIsNumeric($after['yesterday']['income']);

        $this->assertSame(
            (int) $baseline['todos']['unpaid_invoices'] + 1,
            (int) $after['todos']['unpaid_invoices'],
            '新增一张待支付账单后待办计数应 +1'
        );
        $this->assertSame(
            (int) $baseline['todos']['pending_verifications'] + 1,
            (int) $after['todos']['pending_verifications'],
            '新增一个待审核实名用户后待办计数应 +1'
        );
        $this->assertSame(
            (int) $baseline['todos']['pending_withdrawals'] + 1,
            (int) $after['todos']['pending_withdrawals'],
            '新增一笔待处理提现后待办计数应 +1'
        );
        $this->assertSame(
            (int) $baseline['services']['total'] + 2,
            (int) $after['services']['total'],
            '新增两个服务实例后服务总数应 +2'
        );
        $this->assertSame(
            (int) $baseline['services']['expiring_soon'] + 1,
            (int) $after['services']['expiring_soon'],
            '只有 7 天内到期的活跃服务计入即将到期'
        );
        $this->assertEqualsWithDelta(
            (float) $baseline['today']['income'] + 100.0,
            (float) $after['today']['income'],
            0.001,
            '今日营业额应包含今日已付账单'
        );
    }

    public function test_yesterday_block_tracks_previous_day_paid_amount(): void
    {
        $owner = $this->makeUser();

        $baseline = $this->fetchStats();

        $this->makeInvoice($owner, InvoiceStatus::PAID, '66.00', now()->subDay());

        $after = $this->fetchStats();

        $this->assertEqualsWithDelta(
            (float) $baseline['yesterday']['income'] + 66.0,
            (float) $after['yesterday']['income'],
            0.001,
            '昨日营业额应包含昨日已付账单'
        );
        $this->assertEqualsWithDelta(
            (float) $baseline['today']['income'],
            (float) $after['today']['income'],
            0.001,
            '昨日已付账单不应计入今日营业额'
        );
    }

    /**
     * @return array<string, array<string, int|float>>
     */
    private function fetchStats(): array
    {
        Cache::flush();

        return (array) $this->getJson('/api/v2/admin/dashboard/stats')
            ->assertOk()
            ->json('data');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'email' => 'dashboard-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'dashboard-tester',
            'total_sales_amount' => 0,
        ], $attributes));
    }

    private function makeInvoice(User $user, int $status, string $amount, mixed $paidAt = null): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'INV-'.uniqid(),
            'user_id' => $user->id,
            'type' => InvoiceType::NEW_PURCHASE,
            'amount' => $amount,
            'paid_amount' => $status === InvoiceStatus::PAID ? $amount : '0.00',
            'status' => $status,
            'paid_at' => $paidAt,
        ]);
    }

    private function makeProduct(): Product
    {
        return Product::query()->create([
            'product_type' => ProductType::CLOUD_SERVER,
            'service_type_code' => ProductType::CLOUD_SERVER,
            'status' => 1,
            'stock' => -1,
            'pricing' => ['monthly' => '10.00'],
        ]);
    }

    private function makeService(User $user, Product $product, int $status, mixed $expiresAt): Service
    {
        return Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => 'dashboard-svc-'.uniqid(),
            'billing_cycle' => 'monthly',
            'amount' => '10.00',
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    private function makeAdmin(): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '仪表盘只读角色',
            'permissions' => [AdminPermissions::DASHBOARD_VIEW],
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '仪表盘测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
