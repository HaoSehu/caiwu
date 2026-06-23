<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\ServiceStatus;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Automation\AutoRenewService;
use App\Services\Finance\PaymentService;
use App\Services\Provisioning\ServiceRenewService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AutoRenewServiceTest extends TestCase
{
    #[Test]
    public function it_retries_paid_unfulfilled_renew_invoice_without_charging_balance_again(): void
    {
        $suffix = bin2hex(random_bytes(4));

        Service::query()->update(['auto_renew' => 0]);

        $user = User::query()->create([
            'email' => 'auto-renew-retry-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Auto Renew Retry',
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

        $product = Product::query()->create([
            'name' => 'Auto Renew Retry Product '.$suffix,
            'product_type' => 'vps',
            'pricing' => ['monthly' => '49.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'status' => 1,
            'auto_setup' => 0,
        ]);

        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'name' => 'Auto Renew Retry Service '.$suffix,
            'domain' => 'auto-renew-retry-'.$suffix.'.example.com',
            'billing_cycle' => 'monthly',
            'amount' => '49.00',
            'status' => ServiceStatus::ACTIVE,
            'locked_pricing' => ['monthly' => '49.00'],
            'provision_data' => [
                'renew_error' => '上游续费失败，请联系管理员处理',
                'renew_fulfillment_status' => 'failed',
            ],
            'expires_at' => Carbon::now()->addMinutes(2),
            'auto_renew' => 1,
        ]);

        $invoice = Invoice::query()->create([
            'invoice_no' => 'INVAUTORETRY'.strtoupper($suffix),
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'service_id' => (int) $service->id,
            'type' => 'renew',
            'amount' => '49.00',
            'paid_amount' => '49.00',
            'billing_cycle' => 'monthly',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'due_date' => now()->addDay(),
            'config_snapshot' => [
                'auto_renew' => 1,
                'renew_fulfillment_status' => 'failed',
            ],
        ]);

        $renewService = $this->createMock(ServiceRenewService::class);
        $renewService->expects($this->once())
            ->method('createRenewInvoiceForUser')
            ->with(
                $this->callback(fn ($model) => $model instanceof User && (int) $model->id === (int) $user->id),
                (int) $service->id,
                'monthly',
                0,
                $this->callback(fn (array $context): bool => ($context['auto_renew'] ?? false) === true)
            )
            ->willReturn($invoice);
        $renewService->expects($this->once())
            ->method('processPaidRenewInvoice')
            ->with($this->callback(fn ($model) => $model instanceof Invoice && (int) $model->id === (int) $invoice->id))
            ->willReturn($service);
        $renewService->expects($this->once())
            ->method('isRenewInvoiceFulfilled')
            ->with(
                $this->callback(fn ($model) => $model instanceof Invoice && (int) $model->id === (int) $invoice->id),
                $this->callback(fn ($model) => $model instanceof Service && (int) $model->id === (int) $service->id)
            )
            ->willReturn(true);

        $paymentService = $this->createMock(PaymentService::class);
        $paymentService->expects($this->never())->method('payByBalance');

        $summary = (new AutoRenewService($renewService, $paymentService))->handle(10);

        $this->assertSame(1, $summary['matched']);
        $this->assertSame(0, $summary['paid']);
        $this->assertSame(1, $summary['recovered']);
    }
}
