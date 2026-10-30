<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Models\AdminUser;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理端取消订单（N3-02-D，2026-10-28）。
 *
 * 背景：挂账订单被豁免支付窗口自动清理（OrderService::cancelExpiredPendingOrder 的
 * admin_manual 早返回），关联账单被取消后订单会永久停在 PENDING；这类订单只能靠
 * 管理端显式取消。本用例锁定该人工出口的三件事：
 * 1. 权限码 order.manage 把关（此前是预留码，无任何路由）；
 * 2. 取消走 OrderService::cancel()：有账单则级联取消账单与待支付支付单，无账单自行取消；
 * 3. 状态机与幂等：非待支付订单被拒，已取消订单重放不重复写审计。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminOrderCancelTest extends TestCase
{
    use DatabaseTransactions;

    public function test_cancel_requires_order_manage_permission(): void
    {
        $user = User::factory()->create();
        $order = $this->makePendingOrder($user, null, false);

        // 仅持 order.list/order.detail（ORDER_MANAGE 的 implied 子权限）不得取消
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::ORDER_LIST, AdminPermissions::ORDER_DETAIL]));

        $this->postJson('/api/v2/admin/orders/'.$order->id.'/cancellations')
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);

        $this->assertSame(OrderStatus::PENDING, (int) $order->fresh()->status);
    }

    public function test_cancel_pending_order_with_invoice_cascades_invoice_cancellation(): void
    {
        $user = User::factory()->create();
        $order = $this->makePendingOrder($user, null, true);
        $invoice = $this->makeUnpaidInvoice($user, $order, InvoiceType::MANUAL);

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::ORDER_MANAGE]));

        $response = $this->postJson('/api/v2/admin/orders/'.$order->id.'/cancellations')->assertOk();

        $response->assertJsonPath('message', '订单已取消');
        $response->assertJsonPath('data.detail.type', 'cancellation');
        $response->assertJsonPath('data.detail.order.status', OrderStatus::CANCELLED);
        $response->assertJsonPath('data.detail.invoice.id', (int) $invoice->id);

        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status);
        $this->assertSame(InvoiceStatus::CANCELLED, (int) $invoice->fresh()->status);

        // 审计口径：服务端固定写 admin_order_cancel，不接受前端传值
        $log = DB::table('activity_logs')
            ->where('action', 'order.cancel')
            ->where('subject_id', (int) $order->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('admin', (string) $log->actor_type);
        $context = json_decode((string) $log->context, true);
        $this->assertSame('admin_order_cancel', $context['reason'] ?? null);
    }

    public function test_cancel_pending_order_without_invoice_is_supported(): void
    {
        $user = User::factory()->create();
        // 管理端「只建订单不建账单」：挂账订单没有关联账单，正是这条通道的主要对象
        $order = $this->makePendingOrder($user, null, false);

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::ORDER_MANAGE]));

        $this->postJson('/api/v2/admin/orders/'.$order->id.'/cancellations')
            ->assertOk()
            ->assertJsonPath('data.detail.invoice', null);

        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status);
    }

    public function test_cancel_rejects_non_pending_order(): void
    {
        $user = User::factory()->create();
        $order = $this->makePendingOrder($user, null, true);
        $order->forceFill(['status' => OrderStatus::PAID])->save();

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::ORDER_MANAGE]));

        $this->postJson('/api/v2/admin/orders/'.$order->id.'/cancellations')
            ->assertStatus(422)
            ->assertJsonPath('message', '仅待支付订单可取消');

        $this->assertSame(OrderStatus::PAID, (int) $order->fresh()->status);
    }

    public function test_cancel_is_idempotent_on_replay(): void
    {
        $user = User::factory()->create();
        $order = $this->makePendingOrder($user, null, true);
        $invoice = $this->makeUnpaidInvoice($user, $order, InvoiceType::MANUAL);

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::ORDER_MANAGE]));

        $this->postJson('/api/v2/admin/orders/'.$order->id.'/cancellations')->assertOk();

        // D-6：重放走 AdminOrderActionV2Service 的「已取消」短路分支，不重新取一次
        // 取消前的内存态，响应体必须是取消后的真实状态，否则前端拿到旧状态会继续显示待支付。
        $this->postJson('/api/v2/admin/orders/'.$order->id.'/cancellations')
            ->assertOk()
            ->assertJsonPath('message', '订单已取消')
            ->assertJsonPath('data.detail.type', 'cancellation')
            ->assertJsonPath('data.detail.order.id', (int) $order->id)
            ->assertJsonPath('data.detail.order.status', OrderStatus::CANCELLED)
            ->assertJsonPath('data.detail.invoice.id', (int) $invoice->id)
            ->assertJsonPath('data.detail.invoice.status', InvoiceStatus::CANCELLED);

        $this->assertSame(OrderStatus::CANCELLED, (int) $order->fresh()->status);
        $this->assertSame(1, DB::table('activity_logs')
            ->where('action', 'order.cancel')
            ->where('subject_id', (int) $order->id)
            ->count());
    }

    public function test_cancel_rejects_when_invoice_became_paid_concurrently(): void
    {
        // D-6：与支付回调竞态——订单仍是待支付，但账单已在这中间被入账。
        // 取消必须失败并保留订单原状态，不能把已付账单级联取消掉。
        $user = User::factory()->create();
        $order = $this->makePendingOrder($user, null, true);
        $invoice = $this->makeUnpaidInvoice($user, $order, InvoiceType::MANUAL);
        $invoice->forceFill(['status' => InvoiceStatus::PAID])->save();

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::ORDER_MANAGE]));

        $this->postJson('/api/v2/admin/orders/'.$order->id.'/cancellations')
            ->assertStatus(422)
            ->assertJsonPath('message', '当前账单状态不支持取消订单');

        $this->assertSame(OrderStatus::PENDING, (int) $order->fresh()->status);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->fresh()->status);
    }

    public function test_cancel_is_rate_limited(): void
    {
        // D-6：路由声明了 throttle:20,1,admin-order-cancel，必须有用例锁住，
        // 否则误删中间件不会被任何测试发现。第 21 次请求应被限流。
        $user = User::factory()->create();
        $order = $this->makePendingOrder($user, null, false);

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::ORDER_MANAGE]));

        for ($attempt = 1; $attempt <= 20; $attempt++) {
            $this->postJson('/api/v2/admin/orders/'.$order->id.'/cancellations')
                ->assertOk();
        }

        $this->postJson('/api/v2/admin/orders/'.$order->id.'/cancellations')
            ->assertStatus(429);
    }

    private function makePendingOrder(User $user, ?Product $product, bool $adminManual): Order
    {
        $product ??= Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);

        return Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'type' => OrderType::NEW,
            'amount' => 35.00,
            'quantity' => 1,
            'status' => OrderStatus::PENDING,
            'config_snapshot' => $adminManual ? ['admin_manual' => true] : null,
        ]);
    }

    private function makeUnpaidInvoice(User $user, Order $order, string $type): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'order_id' => $order->id,
            'product_id' => $order->product_id,
            'type' => $type,
            'amount' => 35.00,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(7),
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function makeAdmin(array $permissions): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '订单取消测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '订单取消测试管理员',
            'status' => 1,
            'role_id' => (int) $role->id,
        ]);
    }
}
