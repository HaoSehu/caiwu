<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 客户端账单/订单详情越权回归（I-06 数据隔离缺测试缺口）。
 *
 * 背景：客户端资源隔离当前由各 Service 内的 `where('user_id', ...)` 收敛
 * （控制器把 `$request->user()` 传入 Service），没有 Policy 层。隔离逻辑本身正确，
 * 但缺少「用户 A 读不到用户 B 数据」的断言——一旦某条查询漏加 user_id 条件，
 * 不会有任何测试变红。本测试锁定这条底线。
 *
 * 覆盖 GET /api/v2/client/invoices/{invoice} 与 GET /api/v2/client/orders/{id}。
 */
class ClientResourceIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_cannot_read_another_users_invoice(): void
    {
        $owner = $this->makeUser('invoice-owner');
        $intruder = $this->makeUser('invoice-intruder');
        $invoice = $this->makeInvoice($owner);

        Sanctum::actingAs($intruder);

        $this->getJson('/api/v2/client/invoices/'.$invoice->id)
            ->assertNotFound();
    }

    public function test_user_cannot_read_another_users_order(): void
    {
        $owner = $this->makeUser('order-owner');
        $intruder = $this->makeUser('order-intruder');
        $order = $this->makeOrder($owner);

        Sanctum::actingAs($intruder);

        $this->getJson('/api/v2/client/orders/'.$order->id)
            ->assertNotFound();
    }

    public function test_user_can_read_own_invoice_and_order(): void
    {
        $owner = $this->makeUser('self-owner');
        $invoice = $this->makeInvoice($owner);
        $order = $this->makeOrder($owner);

        Sanctum::actingAs($owner);

        // 正向对照：隔离不能误伤本人访问
        $this->getJson('/api/v2/client/invoices/'.$invoice->id)->assertOk();
        $this->getJson('/api/v2/client/orders/'.$order->id)->assertOk();
    }

    private function makeInvoice(User $user): Invoice
    {
        return Invoice::query()->create([
            'invoice_no' => 'INV-ISO-'.uniqid(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => '100.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
        ]);
    }

    private function makeOrder(User $user): Order
    {
        return Order::query()->create([
            'order_no' => 'ORD-ISO-'.uniqid(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => '100.00',
            'status' => 1,
        ]);
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix,
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }
}
