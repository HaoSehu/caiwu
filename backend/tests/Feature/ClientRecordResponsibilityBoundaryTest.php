<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientRecordResponsibilityBoundaryTest extends TestCase
{
    public function test_client_recharge_records_only_include_external_recharge_payments(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $user = $this->createClientUser('record-boundary-'.$suffix);

        $order = Order::query()->create([
            'order_no' => 'ORDRB'.$suffix,
            'user_id' => (int) $user->id,
            'type' => 'new',
            'amount' => '99.00',
            'discount' => '0.00',
            'paid_amount' => '99.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PAID,
            'paid_at' => now(),
        ]);

        $purchaseInvoice = Invoice::query()->create([
            'invoice_no' => 'INVRBP'.$suffix,
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'new',
            'amount' => '99.00',
            'paid_amount' => '99.00',
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDay(),
            'paid_at' => now(),
        ]);

        $purchasePayment = Payment::query()->create([
            'payment_no' => 'PAYBUY'.$suffix,
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'invoice_id' => (int) $purchaseInvoice->id,
            'gateway' => 'alipay',
            'trade_no' => 'TRADEBUY'.$suffix,
            'amount' => '99.00',
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
        ]);

        $rechargeInvoice = Invoice::query()->create([
            'invoice_no' => 'INVRBR'.$suffix,
            'user_id' => (int) $user->id,
            'type' => 'recharge',
            'amount' => '200.00',
            'paid_amount' => '200.00',
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDay(),
            'paid_at' => now(),
        ]);

        $alipayRecharge = Payment::query()->create([
            'payment_no' => 'PAYREC'.$suffix,
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $rechargeInvoice->id,
            'gateway' => 'alipay',
            'trade_no' => 'TRADEREC'.$suffix,
            'amount' => '200.00',
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
        ]);

        $wechatPendingRecharge = Payment::query()->create([
            'payment_no' => 'PAYWXR'.$suffix,
            'user_id' => (int) $user->id,
            'invoice_id' => null,
            'gateway' => 'wechat',
            'trade_no' => null,
            'amount' => '300.00',
            'status' => PaymentStatus::PENDING,
        ]);

        Payment::query()->create([
            'payment_no' => 'PAYMAN'.$suffix,
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $rechargeInvoice->id,
            'gateway' => 'manual',
            'amount' => '50.00',
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/client/payments?page_size=20')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.list.0.gateway', 'wechat')
            ->assertJsonPath('data.list.1.gateway', 'alipay');

        $paymentNos = collect($response->json('data.list'))->pluck('payment_no')->all();
        $this->assertContains((string) $alipayRecharge->payment_no, $paymentNos);
        $this->assertContains((string) $wechatPendingRecharge->payment_no, $paymentNos);
        $this->assertNotContains((string) $purchasePayment->payment_no, $paymentNos);

        $this->getJson('/api/client/payments/summary')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.success', 1);
    }

    public function test_client_order_records_are_purchase_service_orders_not_recharge_invoices(): void
    {
        $suffix = strtoupper(bin2hex(random_bytes(4)));
        $user = $this->createClientUser('order-boundary-'.$suffix);

        $order = Order::query()->create([
            'order_no' => 'ORDOB'.$suffix,
            'user_id' => (int) $user->id,
            'product_spec_snapshot' => '边界测试云服务器',
            'type' => 'new',
            'amount' => '88.00',
            'discount' => '0.00',
            'paid_amount' => '0.00',
            'billing_cycle' => 'monthly',
            'status' => OrderStatus::PENDING,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INVOB'.$suffix,
            'user_id' => (int) $user->id,
            'order_id' => (int) $order->id,
            'type' => 'new',
            'amount' => '88.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDay(),
        ]);

        Invoice::query()->create([
            'invoice_no' => 'INVRECH'.$suffix,
            'user_id' => (int) $user->id,
            'type' => 'recharge',
            'amount' => '120.00',
            'paid_amount' => '120.00',
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDay(),
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/client/orders?page_size=20')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.order_no', (string) $order->order_no)
            ->assertJsonPath('data.list.0.invoice_id', (int) $invoice->id)
            ->assertJsonPath('data.list.0.product_name', '边界测试云服务器');

        $this->getJson('/api/client/orders?keyword='.$invoice->invoice_no.'&page_size=20')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.order_no', (string) $order->order_no);
    }

    private function createClientUser(string $seed): User
    {
        return User::query()->create([
            'email' => strtolower($seed).'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Record Boundary',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);
    }
}
