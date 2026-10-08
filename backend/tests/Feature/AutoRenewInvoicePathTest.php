<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\OrderStatus;
use App\Constants\ServiceStatus;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Automation\AutoRenewService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 自动续费切账单路径（方案 2 阶段二）回归：
 * AutoRenewService 由「订单优先」（createRenewOrderForUser + payOrderByBalance）
 * 切换为「账单优先」（createRenewInvoiceForUser + payByBalance），行为契约不变：
 * ①已支付未履约 → 复用跳过；②余额不足 → 挂起待重试；③余额充足 → 建账单扣款置已支付。
 * 使用 DatabaseTransactions，测试结束回滚；履约派发已 mock 隔离。
 */
class AutoRenewInvoicePathTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // 履约派发隔离：本测试锁定扣款与账单口径，开通履约不在范围内
        $this->mock(PaidOrderBusinessFlowDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchPaidInvoice')->andReturnNull();
        });
    }

    public function test_auto_renew_charges_balance_via_invoice_path(): void
    {
        [$user, $service] = $this->fixture(40.00);

        app(AutoRenewService::class)->handle();

        $invoice = Invoice::query()
            ->where('service_id', (int) $service->id)
            ->where('type', 'renew')
            ->latest('id')
            ->firstOrFail();

        // 资金口径：账单置已支付、余额扣减
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);
        $this->assertSame('40.00', number_format((float) $invoice->amount, 2, '.', ''));
        $this->assertSame('0.00', number_format((float) $user->fresh()->balance, 2, '.', ''));

        // 履约工单：影子订单随账单建立并同步已支付
        $this->assertNotNull($invoice->order_id, '续费账单必须绑定影子订单');
        $this->assertSame(OrderStatus::PAID, (int) $invoice->order?->status);

        // 自动续费台账：本次扣款登记 executed
        $this->assertTrue(
            AutomationLog::query()
                ->where('task_key', 'auto_renew')
                ->where('action', 'charge')
                ->where('object_id', (int) $service->id)
                ->whereNotNull('executed_at')
                ->exists(),
            '自动续费扣款必须登记 AutomationLog'
        );
    }

    public function test_auto_renew_skips_when_paid_unfulfilled_renew_invoice_exists(): void
    {
        [$user, $service] = $this->fixture(40.00);

        // 已支付未履约的续费账单：本轮必须复用跳过，不得重复建单扣款
        $paidInvoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'service_id' => (int) $service->id,
            'product_id' => (int) $service->product_id,
            'type' => 'renew',
            'billing_cycle' => 'monthly',
            'amount' => '40.00',
            'paid_amount' => '40.00',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
            'due_date' => now()->addDay(),
        ]);
        $paidOrder = Order::query()->create([
            'order_no' => Order::generateOrderNo(),
            'user_id' => (int) $user->id,
            'product_id' => (int) $service->product_id,
            'service_id' => (int) $service->id,
            'type' => 'renew',
            'billing_cycle' => 'monthly',
            'amount' => '40.00',
            'status' => OrderStatus::PAID,
        ]);
        $paidInvoice->forceFill(['order_id' => (int) $paidOrder->id])->save();

        app(AutoRenewService::class)->handle();

        $invoiceCount = Invoice::query()
            ->where('service_id', (int) $service->id)
            ->where('type', 'renew')
            ->count();
        $this->assertSame(1, $invoiceCount, '复用跳过不得新建续费账单');
        $this->assertSame('40.00', number_format((float) $user->fresh()->balance, 2, '.', ''), '复用跳过不得扣减余额');
    }

    public function test_auto_renew_pends_on_insufficient_balance(): void
    {
        [$user, $service] = $this->fixture(5.00);

        app(AutoRenewService::class)->handle();

        $invoiceCount = Invoice::query()
            ->where('service_id', (int) $service->id)
            ->where('type', 'renew')
            ->count();
        $this->assertSame(0, $invoiceCount, '余额不足不得建续费账单');
        $this->assertSame('5.00', number_format((float) $user->fresh()->balance, 2, '.', ''), '余额不足不得扣款');
    }

    /**
     * @return array{0: User, 1: Service}
     */
    private function fixture(float $balance): array
    {
        $user = User::factory()->create(['is_verified' => 1]);
        app(AccountService::class)->setCashBalance($user, $balance);

        $product = Product::query()->create([
            'name' => '自动续费契约商品'.uniqid(),
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '40.00'],
            'setup_fee' => '0.00',
            'config_options' => [],
            'purchase_requires' => [],
            'stock' => -1,
            'auto_setup' => 0,
        ]);
        $service = Service::query()->create([
            'user_id' => (int) $user->id,
            'product_id' => (int) $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 40.00,
            'status' => ServiceStatus::ACTIVE,
            'auto_renew' => 1,
            // 自动续费窗口：expires_at 落在 now()+auto_renew_days_before 当天
            'expires_at' => now()->addDays((int) config('idc.auto_renew_days_before', 3)),
        ]);

        return [$user, $service];
    }
}
