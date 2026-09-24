<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\OrderStatus;
use App\Constants\OrderType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Admin\V2\AdminManualEntryV2Service;
use App\Services\Finance\OrderV2QueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 管理端补录订单（仅续费/附加配置）：订单+账单双写并直接入账，
 * 固定不触发开通/续期业务流转（实例到期时间不变）。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class AdminManualOrderEntryTest extends TestCase
{
    public function test_renew_entry_creates_paid_order_invoice_and_payment(): void
    {
        $user = $this->makeUser();
        $service = $this->makeService($user);
        $expiresAt = $service->expires_at;

        $result = $this->service()->createManualOrder($user, [
            'service_id' => (int) $service->id,
            'type' => OrderType::RENEW,
            'amount' => '200.00',
            'billing_cycle' => 'monthly',
            'trade_no' => 'RENEW-'.uniqid(),
            'payment_gateway' => 'cash',
            'remark' => '线下续费补录',
        ], $this->context());

        $this->assertSame('completed', $result['status']);

        $order = Order::query()->findOrFail((int) $result['detail']['order']['id']);
        $this->assertSame(OrderStatus::PAID, (int) $order->status);
        $this->assertSame(OrderType::RENEW, (string) $order->type);
        $this->assertSame((int) $service->id, (int) $order->service_id);
        $this->assertSame('线下续费补录', (string) $order->remark);

        /** @var Invoice $invoice */
        $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(InvoiceType::RENEW, (string) $invoice->type);
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('200.00', number_format((float) $invoice->amount, 2, '.', ''));

        $payment = Payment::query()->where('invoice_id', $invoice->id)->where('status', PaymentStatus::SUCCESS)->firstOrFail();
        $this->assertSame(PaymentGatewayCode::MANUAL, $payment->gatewayKey());
        $this->assertSame('cash', ((array) $payment->callback_raw)['payment_gateway']);

        // 固定不开通：补录仅记账，实例到期时间不得被推进。
        $this->assertSame(
            $expiresAt?->format('Y-m-d H:i:s'),
            $service->fresh()?->expires_at?->format('Y-m-d H:i:s')
        );
    }

    public function test_upgrade_entry_projects_upgrade_invoice_type(): void
    {
        $user = $this->makeUser();
        $service = $this->makeService($user);

        $result = $this->service()->createManualOrder($user, [
            'service_id' => (int) $service->id,
            'type' => OrderType::UPGRADE,
            'amount' => '66.00',
            'remark' => '升级补录',
        ], $this->context());

        $order = Order::query()->findOrFail((int) $result['detail']['order']['id']);

        $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(InvoiceType::UPGRADE, (string) $invoice->type);
    }

    public function test_rejects_service_owned_by_other_user(): void
    {
        $owner = $this->makeUser();
        $service = $this->makeService($owner);
        $other = $this->makeUser();

        $this->expectException(BusinessException::class);
        $this->service()->createManualOrder($other, [
            'service_id' => (int) $service->id,
            'type' => OrderType::RENEW,
            'amount' => '100.00',
            'remark' => '越权补录',
        ], $this->context());
    }

    public function test_rejects_new_type(): void
    {
        $user = $this->makeUser();
        $service = $this->makeService($user);

        $this->expectException(BusinessException::class);
        $this->service()->createManualOrder($user, [
            'service_id' => (int) $service->id,
            'type' => OrderType::NEW,
            'amount' => '100.00',
            'remark' => '新购不允许补录',
        ], $this->context());
    }

    public function test_rejects_when_pending_order_exists_for_service(): void
    {
        $user = $this->makeUser();
        $service = $this->makeService($user);

        Order::query()->create([
            'order_no' => 'OP'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'service_id' => $service->id,
            'type' => OrderType::RENEW,
            'amount' => 100.00,
            'status' => OrderStatus::PENDING,
        ]);

        $this->expectException(BusinessException::class);
        $this->service()->createManualOrder($user, [
            'service_id' => (int) $service->id,
            'type' => OrderType::RENEW,
            'amount' => '100.00',
            'remark' => '存在待支付订单',
        ], $this->context());
    }

    public function test_user_order_filter_scopes_to_route_user(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        $this->makeOrder($userA, OrderStatus::PENDING);
        $this->makeOrder($userA, OrderStatus::PAID);
        $this->makeOrder($userB, OrderStatus::PAID);

        $paginator = app(OrderV2QueryService::class)
            ->paginateAdminOrders(['user_id' => (int) $userA->id]);

        $this->assertSame(2, $paginator->total());
    }

    private function service(): AdminManualEntryV2Service
    {
        return app(AdminManualEntryV2Service::class);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'manual-order-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'manual-order-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeService(User $user): Service
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
            'name' => 'svc-'.uniqid(),
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
            'status' => 1,
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function makeOrder(User $user, int $status): Order
    {
        return Order::query()->create([
            'order_no' => 'OU'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => OrderType::RENEW,
            'amount' => 100.00,
            'status' => $status,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'operator_id' => 1,
            'operator_name' => 'admin-test',
            'trace_id' => 'test-'.uniqid(),
            'ip_address' => '127.0.0.1',
        ];
    }
}
