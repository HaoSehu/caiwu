<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderType;
use App\Constants\ServiceStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Provisioning\ServiceRenewService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 手动续费路径命中"已支付但履约未完成"的续费账单时拒绝复用并抛异常，
 * 避免把用户送去已支付账单的支付页得到"无需支付"死胡同；自动路径保持复用防双扣。
 */
class ServiceRenewBlockingPaidInvoiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_manual_renew_rejects_blocking_paid_invoice(): void
    {
        [$user, $service] = $this->renewFixture();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('该周期已有一笔正在处理的续费');

        app(ServiceRenewService::class)->createRenewInvoiceForUser(
            $user,
            (int) $service->id,
            (string) $service->billing_cycle,
            0,
            [],
            true
        );
    }

    public function test_auto_path_reuses_blocking_paid_invoice(): void
    {
        [$user, $service, $invoice] = $this->renewFixture();

        $reused = app(ServiceRenewService::class)->createRenewInvoiceForUser(
            $user,
            (int) $service->id,
            (string) $service->billing_cycle,
            0,
            []
        );

        $this->assertSame((int) $invoice->id, (int) $reused->id);
    }

    public function test_blocking_lookup_returns_null_when_fulfilled(): void
    {
        [$user, $service, $invoice] = $this->renewFixture();
        $service->forceFill([
            'provision_data' => ['last_renew_invoice_id' => (int) $invoice->id],
        ])->save();

        $this->assertNull($this->invokeBlockingLookup($user, $service, (string) $service->billing_cycle));
    }

    public function test_blocking_lookup_returns_null_when_fulfillment_failed(): void
    {
        [$user, $service, $invoice] = $this->renewFixture();
        $invoice->forceFill([
            'config_snapshot' => ['renew_fulfillment_status' => 'failed'],
        ])->save();

        $this->assertNull($this->invokeBlockingLookup($user, $service, (string) $service->billing_cycle));
    }

    public function test_blocking_lookup_returns_invoice_when_pending(): void
    {
        [$user, $service, $invoice] = $this->renewFixture();

        $blocking = $this->invokeBlockingLookup($user, $service, (string) $service->billing_cycle);

        $this->assertInstanceOf(Invoice::class, $blocking);
        $this->assertSame((int) $invoice->id, (int) $blocking->id);
    }

    private function invokeBlockingLookup(User $user, Service $service, string $cycle): ?Invoice
    {
        $method = new ReflectionMethod(ServiceRenewService::class, 'findBlockingPaidRenewInvoice');

        return $method->invoke(app(ServiceRenewService::class), $user, $service, $cycle, 0);
    }

    /**
     * @return array{0: User, 1: Service, 2: Invoice}
     */
    private function renewFixture(): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
        $invoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'service_id' => $service->id,
            'type' => OrderType::RENEW,
            'amount' => 35.00,
            'status' => InvoiceStatus::PAID,
            'billing_cycle' => 'monthly',
        ]);

        return [$user, $service, $invoice];
    }
}
