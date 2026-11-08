<?php

declare(strict_types=1);

namespace TestsFeature;

use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 服务控制台「相关账单 / 操作日志」两个页签的管理端接口：
 * 1. 账单支持 service_id 过滤，只返回该实例的数据，不再退化为用户全量；
 * 2. 实例级操作日志返回管理员操作（includeAdminOperations=true），
 *    而客户端口径仍会过滤掉 actor_type=admin 的记录。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminServiceConsoleTabsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invoices_can_be_filtered_by_service_id(): void
    {
        // 用户发票列表已合并到 /admin/invoices?user_id=，该入口由 INVOICE_LIST 守卫。
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_DETAIL, AdminPermissions::INVOICE_LIST]));

        $user = $this->makeUser('console-invoices');
        $service = $this->makeService($user, '目标实例');
        $otherService = $this->makeService($user, '其它实例');

        $invoice = $this->makeInvoice($user, (int) $service->id);
        $this->makeInvoice($user, (int) $otherService->id);

        $this->getJson("/api/v2/admin/invoices?user_id={$user->id}&service_id={$service->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.invoice_no', (string) $invoice->invoice_no);
    }

    public function test_service_operation_logs_include_admin_operations(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::USER_DETAIL]);
        $user = $this->makeUser('console-logs');
        $service = $this->makeService($user, '日志实例');
        Sanctum::actingAs($admin);

        $this->writeServiceLog($service, 'service.console.tracking.create', [
            'category' => 'service',
            'summary' => '新增历史跟踪记录',
            'actor_type' => 'admin',
            'actor_name' => (string) $admin->username,
        ]);
        $this->writeServiceLog($service, 'service.console.power.on', [
            'category' => 'power',
            'summary' => '客户开机',
            'actor_type' => 'client',
            'actor_name' => '客户本人',
        ]);

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/operation-logs")
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.summary.service_name', '日志实例');

        $actions = collect($this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/operation-logs")->json('data.list'))
            ->pluck('action')
            ->all();
        $this->assertContains('service.console.tracking.create', $actions, '管理端必须能看到管理员操作日志');
        $this->assertContains('service.console.power.on', $actions);
    }

    public function test_service_operation_logs_filter_by_category_and_keyword(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::USER_DETAIL]);
        $user = $this->makeUser('console-logs-filter');
        $service = $this->makeService($user, '日志过滤实例');
        Sanctum::actingAs($admin);

        $this->writeServiceLog($service, 'service.console.tracking.create', [
            'category' => 'service',
            'summary' => '新增历史跟踪记录',
            'actor_type' => 'admin',
        ]);
        $this->writeServiceLog($service, 'service.console.power.on', [
            'category' => 'power',
            'summary' => '客户开机',
            'actor_type' => 'client',
        ]);

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/operation-logs?category=service")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.action', 'service.console.tracking.create');

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/operation-logs?keyword=开机")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.summary', '客户开机');
    }

    public function test_service_operation_logs_of_another_user_returns_404(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_DETAIL]));

        $owner = $this->makeUser('console-logs-owner');
        $other = $this->makeUser('console-logs-other');
        $service = $this->makeService($owner, '他人实例');

        $this->getJson("/api/v2/admin/users/{$other->id}/services/{$service->id}/operation-logs")
            ->assertStatus(404)
            ->assertJsonPath('code', 40400)
            ->assertJsonPath('message', '服务不存在');
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function writeServiceLog(Service $service, string $action, array $detail): void
    {
        ActivityLog::query()->create([
            'event_id' => (string) Str::ulid(),
            'stream' => 'business',
            'actor_type' => (string) ($detail['actor_type'] ?? 'client'),
            'actor_id' => 1,
            'actor_name' => (string) ($detail['actor_name'] ?? '客户'),
            'module' => 'service',
            'action' => $action,
            'description' => '[service] '.$action,
            'subject_type' => 'service',
            'subject_id' => (int) $service->id,
            'context' => array_merge([
                'service_id' => (int) $service->id,
                'service_name' => (string) $service->name,
            ], $detail),
            'ip_address' => '127.0.0.1',
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function makeAdmin(array $permissions = [AdminPermissions::USER_DETAIL]): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '服务控制台页签测试角色',
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
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeService(User $user, string $name): Service
    {
        $product = Product::query()->create([
            'product_group_id' => null,
            'service_type_code' => 'test',
            'product_type' => 'other',
            'pricing' => ['monthly' => 100.00],
            'setup_fee' => 0,
            'status' => 1,
            'sort_order' => 0,
        ]);

        return Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => $name,
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
            'status' => 1,
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function makeInvoice(User $user, int $serviceId): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'SI'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'service_id' => $serviceId,
            'type' => 'new',
            'amount' => 100.00,
            'paid_amount' => 0,
            'status' => 0,
        ]);
    }
}
