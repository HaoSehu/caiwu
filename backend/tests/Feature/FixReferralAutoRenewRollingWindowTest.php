<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\ServiceStatus;
use App\Models\AutomationLog;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\Automation\AutoRenewService;
use App\Services\Order\PaidOrderBusinessFlowDispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 自动续费审查回归（M-89 AUTO-01 / M-90 AUTO-02）：
 * ① 续费窗口改为滚动区间后，余额不足跨日充值可重试并最终扣款成功；
 * ② 已过期转「已暂停(reason=expired)」的服务仍在自动续费可达范围内；
 * ③ 同服务同周期存在「已支付且 requires_refund=true」的续费账单时，
 *    自动续费必须阻断新建/扣款（续费失败放行闸门后的第二道防双扣闸）。
 * 使用 DatabaseTransactions，测试结束回滚；履约派发已 mock 隔离。
 */
class FixReferralAutoRenewRollingWindowTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(PaidOrderBusinessFlowDispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatchPaidInvoice')->andReturnNull();
        });
    }

    public function test_auto_renew_retries_next_day_when_balance_becomes_sufficient(): void
    {
        [$user, $service] = $this->fixture(5.00, now()->addDays((int) config('idc.auto_renew_days_before', 3)));

        app(AutoRenewService::class)->handle();
        $this->assertSame(0, $this->renewInvoiceCount($service), '余额不足不得建续费账单');
        $this->assertSame('5.00', $this->money($user->fresh()->balance), '余额不足不得扣款');

        // 跨日：窗口只按「到期前 N 天」的当天命中时，这一天的调度不会再命中该服务
        $this->travel(1)->days();
        $this->seedFinanceBalance($user, 35.00);

        app(AutoRenewService::class)->handle();

        $this->assertSame(1, $this->renewInvoiceCount($service), '次日充值后自动续费应重试成功');
        $this->assertSame(InvoiceStatus::PAID, (int) $this->latestRenewInvoice($service)->status);
        $this->assertSame('0.00', $this->money($user->fresh()->balance), '扣款后余额应为 0');
    }

    public function test_auto_renew_reaches_expired_suspended_service(): void
    {
        // 已过期：服务已被生命周期任务置为「已暂停(reason=expired)」，expires_at 落在过去
        [$user, $service] = $this->fixture(40.00, now()->subDay());
        $service->forceFill([
            'status' => ServiceStatus::SUSPENDED,
            'suspended_reason' => 'expired',
        ])->save();

        app(AutoRenewService::class)->handle();

        $this->assertSame(1, $this->renewInvoiceCount($service), '过期暂停服务应命中自动续费');
        $this->assertSame(InvoiceStatus::PAID, (int) $this->latestRenewInvoice($service)->status);
        $this->assertSame('0.00', $this->money($user->fresh()->balance));
    }

    public function test_auto_renew_is_blocked_by_paid_renew_invoice_requiring_refund(): void
    {
        [$user, $service] = $this->fixture(40.00, now()->addDays((int) config('idc.auto_renew_days_before', 3)));

        // 上游续费失败：账单已支付、履约 FAILED、requires_refund=true（防双扣闸门被放行）
        $blockedInvoice = $this->createRenewInvoice($user, $service, [
            'status' => InvoiceStatus::PAID,
            'paid_amount' => '40.00',
            'paid_at' => now()->subHours(2),
            'requires_refund' => true,
            'config_snapshot' => ['renew_fulfillment_status' => 'failed'],
        ]);

        app(AutoRenewService::class)->handle();

        $this->assertSame(1, $this->renewInvoiceCount($service), '阻断时不得新建续费账单');
        $this->assertSame(
            (int) $blockedInvoice->id,
            (int) $this->latestRenewInvoice($service)->id,
            '不得产生第二张续费账单'
        );
        $this->assertSame('40.00', $this->money($user->fresh()->balance), '阻断时余额不得变动');
        $this->assertTrue(
            AutomationLog::query()
                ->where('task_key', 'auto_renew')
                ->where('action', 'charge')
                ->where('object_type', 'service')
                ->where('object_id', (int) $service->id)
                ->where('rule_key', 'blocked_requires_refund')
                ->whereNotNull('executed_at')
                ->exists(),
            '阻断必须写 AutomationLog 便于运营核对'
        );
    }

    /**
     * @return array{0: User, 1: Service}
     */
    private function fixture(float $balance, \DateTimeInterface $expiresAt): array
    {
        $user = User::factory()->create(['is_verified' => 1]);
        $this->seedFinanceBalance($user, $balance);

        $product = Product::query()->create([
            'name' => '自动续费滚动窗口商品'.uniqid(),
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
            'expires_at' => $expiresAt,
        ]);

        return [$user, $service];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRenewInvoice(User $user, Service $service, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => (int) $user->id,
            'service_id' => (int) $service->id,
            'product_id' => (int) $service->product_id,
            'type' => 'renew',
            'billing_cycle' => 'monthly',
            'amount' => '40.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDay(),
        ], $overrides));
    }

    private function renewInvoiceCount(Service $service): int
    {
        return Invoice::query()
            ->where('service_id', (int) $service->id)
            ->where('type', 'renew')
            ->count();
    }

    private function latestRenewInvoice(Service $service): Invoice
    {
        return Invoice::query()
            ->where('service_id', (int) $service->id)
            ->where('type', 'renew')
            ->latest('id')
            ->firstOrFail();
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
