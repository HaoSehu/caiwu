<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FinanceLedgerEntry;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\ClientServiceConsole\ServiceDetailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 服务控制台「财务日志」按 service_id 过滤时曾抛 TypeError：
 * whereExists 内层闭包把 Query\Builder 标注成 Eloquent\Builder，接口直接 500。
 * 同时 App 服务详情的「账单」页签只读 services.invoice_id，历史数据为空时显示为空。
 */
class FinanceLedgerServiceFilterTest extends TestCase
{
    use DatabaseTransactions;

    public function test_ledger_filters_by_service_id_without_server_error(): void
    {
        [$user, $service, $invoice] = $this->makePaidOrderFixture();
        $this->makeLedgerEntry($user, $invoice, '20.00');

        Sanctum::actingAs($user);

        $this->getJson("/api/v2/client/ledger?tab=invoices&service_id={$service->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.event_type', 'invoice_payment')
            ->assertJsonPath('data.list.0.invoice.id', (int) $invoice->id);
    }

    public function test_service_detail_falls_back_to_order_invoice(): void
    {
        [$user, $service, $invoice] = $this->makePaidOrderFixture();

        // 走真实详情链路：订单账单的预加载字段必须足够渲染状态与支付时间，
        // 否则回退拿到的账单会因缺少 status/paid_at 显示成「待支付」。
        $detail = app(ServiceDetailService::class)->getBaseDetailForUser($user, (int) $service->id);

        $this->assertSame((int) $invoice->id, (int) $detail['invoice']['id']);
        $this->assertSame((string) $invoice->invoice_no, (string) $detail['invoice']['invoice_no']);
        $this->assertSame(1, (int) $detail['invoice']['status']);
        $this->assertNotNull($detail['invoice']['paid_at']);
    }

    /**
     * 复刻生产数据形态：账单只挂在订单上（invoices.service_id 为空），
     * 服务记录的 invoice_id 也为空，只能通过 order.service_id 关联到服务。
     *
     * @return array{0: User, 1: Service, 2: Invoice}
     */
    private function makePaidOrderFixture(): array
    {
        $user = User::query()->create([
            'email' => 'ledger-service-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'ledger-service-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);

        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '20.00'],
        ]);

        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 20.00,
            'status' => 1,
            'expires_at' => now()->addMonth(),
        ]);

        $order = Order::query()->create([
            'order_no' => 'OF'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'service_id' => $service->id,
            'type' => 'new',
            'amount' => 20.00,
            'paid_amount' => 20.00,
            'status' => 1,
            'paid_at' => now(),
        ]);

        $service->forceFill(['order_id' => $order->id])->save();

        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'order_id' => $order->id,
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => 20.00,
            'paid_amount' => 20.00,
            'status' => 1,
            'paid_at' => now(),
        ]);

        return [$user, $service->fresh(), $invoice];
    }

    private function makeLedgerEntry(User $user, Invoice $invoice, string $amount): FinanceLedgerEntry
    {
        return FinanceLedgerEntry::query()->create([
            'entry_no' => 'LG'.uniqid(),
            'user_id' => $user->id,
            'account_type' => 'cash',
            'event_type' => 'invoice_payment',
            'change_amount' => '-'.$amount,
            'balance_after' => '0.00',
            'source_type' => 'invoice',
            'source_id' => $invoice->id,
            'origin_type' => 'invoice',
            'origin_id' => $invoice->id,
        ]);
    }
}
