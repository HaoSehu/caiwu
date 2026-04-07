<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminOrderNotificationService;
use App\Services\CheckoutSecurityService;
use App\Services\InvoiceService;
use App\Services\NotificationService;
use App\Services\OperationLogService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\ProductCatalogService;
use App\Services\CouponService;
use Tests\TestCase;

class OrderQuantityCheckoutFlowTest extends TestCase
{
    public function test_order_creation_persists_quantity_and_invoice_item_quantity(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = User::query()->create([
            'email' => "order-quantity-{$suffix}@example.com",
            'password' => 'secret123',
            'phone' => '139' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'status' => 1,
        ]);

        $product = Product::query()->create([
            'name' => '数量测试商品',
            'product_type' => 'server',
            'pricing' => ['monthly' => '99.00'],
            'setup_fee' => '10.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => 10,
            'status' => 1,
            'auto_setup' => 0,
        ]);

        $checkoutSecurity = new CheckoutSecurityService();
        $invoiceService = new InvoiceService();

        $productCatalogService = $this->createMock(ProductCatalogService::class);
        $productCatalogService->expects($this->once())
            ->method('assertProductCanBeProvisioned')
            ->with($this->callback(fn (Product $candidate): bool => (int) $candidate->id === (int) $product->id), 2);

        $couponService = $this->createMock(CouponService::class);
        $couponService->method('reserveOwnedCouponForOrder')->willReturn([]);

        $operationLogService = $this->createMock(OperationLogService::class);
        $operationLogService->method('write');

        $adminOrderNotificationService = $this->createMock(AdminOrderNotificationService::class);
        $adminOrderNotificationService->method('notifyOrderCreated');

        $orderService = new OrderService(
            $invoiceService,
            $this->createMock(PaymentService::class),
            $productCatalogService,
            $checkoutSecurity,
            $couponService,
            $operationLogService,
            $this->createMock(NotificationService::class),
            $adminOrderNotificationService,
        );

        $quote = $orderService->quote($product, 'monthly', [], 2);
        $quotePayload = array_merge($quote, [
            'subtotal_amount' => $quote['total_amount'],
        ]);
        $tokenData = $checkoutSecurity->issueQuoteToken($product->id, 'monthly', [], $quotePayload);

        $order = $orderService->create($user->id, [
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'quantity' => 2,
            'config' => [],
            'quote_token' => (string) ($tokenData['quote_token'] ?? ''),
        ], [
            'idempotency_key' => 'order-quantity-' . $suffix,
            'trace_id' => 'trace-order-quantity-' . $suffix,
        ]);

        $order->refresh()->load('invoice');

        $this->assertSame(2, (int) $order->quantity);
        $this->assertSame('218.00', number_format((float) $order->amount, 2, '.', ''));
        $this->assertSame(2, (int) ($order->config_pricing_snapshot['quantity'] ?? 0));
        $this->assertSame('218.00', (string) ($order->config_pricing_snapshot['total_amount'] ?? ''));

        $invoiceItem = InvoiceItem::query()
            ->where('invoice_id', (int) $order->invoice?->id)
            ->first();

        $this->assertNotNull($invoiceItem);
        $this->assertSame(2, (int) $invoiceItem->quantity);
        $this->assertSame('109.00', number_format((float) $invoiceItem->unit_price, 2, '.', ''));
        $this->assertSame('218.00', number_format((float) $invoiceItem->line_amount, 2, '.', ''));
    }

    public function test_quote_token_and_fingerprint_include_quantity(): void
    {
        $service = new CheckoutSecurityService();
        $quotePayload = [
            'quantity' => 2,
            'subtotal_amount' => '198.00',
            'total_amount' => '198.00',
            'base_amount' => '198.00',
            'config_amount' => '0.00',
            'setup_fee' => '0.00',
            'discount_amount' => '0.00',
        ];

        $token = $service->issueQuoteToken(10, 'monthly', [], $quotePayload);

        $service->assertQuoteToken(
            (string) ($token['quote_token'] ?? ''),
            10,
            'monthly',
            2,
            [],
            '198.00',
            '198.00',
            0,
        );

        $this->assertNotSame(
            $service->buildCheckoutFingerprint(10, 'monthly', 1, [], 0),
            $service->buildCheckoutFingerprint(10, 'monthly', 2, [], 0),
        );
    }
}
