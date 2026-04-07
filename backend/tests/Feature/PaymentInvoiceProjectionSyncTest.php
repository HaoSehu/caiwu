<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Tests\TestCase;

class PaymentInvoiceProjectionSyncTest extends TestCase
{
    public function test_invoice_service_create_from_order_still_materializes_invoice_items_without_model_hook(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $order = Order::query()->create([
            'order_no' => 'INVPRJ' . strtoupper($suffix),
            'user_id' => 1,
            'product_id' => null,
            'product_name_snapshot' => '投影测试商品',
            'product_type_snapshot' => 'server',
            'type' => 'new',
            'amount' => '99.00',
            'discount' => '9.00',
            'billing_cycle' => 'monthly',
            'quantity' => 2,
            'status' => 0,
        ]);

        $invoice = app(InvoiceService::class)->createFromOrder($order);
        $invoice->refresh()->load('items');

        $this->assertCount(1, $invoice->items);
        $this->assertSame(2, (int) $invoice->items[0]->quantity);
        $this->assertSame('90.00', number_format((float) $invoice->items[0]->line_amount, 2, '.', ''));
    }

    public function test_payment_service_sync_projection_materializes_payment_callbacks_without_model_hook(): void
    {
        $payment = Payment::query()->create([
            'payment_no' => Payment::generatePaymentNo(),
            'user_id' => 1,
            'invoice_id' => null,
            'gateway' => 'alipay',
            'trade_no' => 'TRADE-' . bin2hex(random_bytes(4)),
            'amount' => '19.90',
            'status' => PaymentStatus::SUCCESS,
            'callback_raw' => [
                'trade_no' => 'TRADE-CALLBACK-' . bin2hex(random_bytes(4)),
                'trade_status' => 'TRADE_SUCCESS',
                'refund' => [
                    'trade_no' => 'REFUND-' . bin2hex(random_bytes(4)),
                    'refunded_at' => now()->format('Y-m-d H:i:s'),
                ],
            ],
            'paid_at' => now(),
        ]);

        app(PaymentService::class)->syncProjection($payment);
        $payment->refresh()->load('callbacks');

        $this->assertCount(2, $payment->callbacks);
        $this->assertNotNull($payment->callbacks->firstWhere('callback_type', 'payment'));
        $this->assertNotNull($payment->callbacks->firstWhere('callback_type', 'refund'));
    }

    public function test_payment_and_invoice_models_no_longer_use_saved_projection_hooks(): void
    {
        $paymentModel = file_get_contents(base_path('app/Models/Payment.php'));
        $invoiceModel = file_get_contents(base_path('app/Models/Invoice.php'));

        $this->assertIsString($paymentModel);
        $this->assertIsString($invoiceModel);
        $this->assertStringNotContainsString('static::saved', $paymentModel);
        $this->assertStringNotContainsString('static::saved', $invoiceModel);
    }
}
