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
use Tests\TestCase;

/**
 * 续费重复拦截判据回归：
 * 旧判据用「最近一次已履约的同周期续费距今是否超过 N 个自然月」窗口，对月付而言
 * 窗口恰好等于正常续费节奏本身，月付续费与自动续费被静默拦截。
 * 新判据按已履约账单的覆盖到期点判定：支付时间按同一周期推进得到 coveredUntil，
 * 只有其晚于服务当前 expires_at（该笔收款尚未反映到有效期）时才拦截；
 * 已完整体现为有效期的续费放行，允许提前续下一周期。
 * 使用 DatabaseTransactions：所有写入在测试结束后回滚，不污染现有数据。
 */
class ServiceRenewNextCycleIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_monthly_renew_is_allowed_when_last_cycle_is_fully_reflected_in_expiry(): void
    {
        // 月付已履约账单支付于 24 天前，到期时间已完整顺延到该笔续费的覆盖期末（+1 月再 +2 天）：
        // 覆盖到期点（24 天前 + 1 月）早于当前到期时间，用户此时续的是下一周期，必须放行。
        $paidAt = now()->subDays(24)->startOfSecond();
        [$user, $service] = $this->renewFixture([
            'paid_at' => $paidAt,
            'expires_at' => $paidAt->copy()->addMonthsNoOverflow(1)->addDays(2),
        ]);

        $invoice = app(ServiceRenewService::class)->createRenewInvoiceForUser(
            $user,
            (int) $service->id,
            'monthly'
        );

        $this->assertSame(InvoiceStatus::UNPAID, (int) $invoice->status);
        $this->assertNotSame($this->fulfilledInvoiceId, (int) $invoice->id);
        $this->assertSame(
            1,
            Invoice::query()
                ->where('service_id', (int) $service->id)
                ->where('type', OrderType::RENEW)
                ->where('status', InvoiceStatus::UNPAID)
                ->count()
        );
    }

    public function test_monthly_renew_is_blocked_while_last_paid_cycle_is_not_yet_reflected_in_expiry(): void
    {
        // 自动续费已扣款并登记履约（provision_data.last_renew_invoice_id），但 expires_at
        // 仍是旧值（履约在途）：支付于 3 天前，覆盖到期点为 27 天后，远晚于当前到期 5 天后，
        // 此时再手动续费就是对同一周期二次收费，必须拦截。
        $paidAt = now()->subDays(3)->startOfSecond();
        [$user, $service] = $this->renewFixture([
            'paid_at' => $paidAt,
            'expires_at' => now()->addDays(5),
        ]);

        try {
            app(ServiceRenewService::class)->createRenewInvoiceForUser(
                $user,
                (int) $service->id,
                'monthly'
            );
            $this->fail('已履约账单尚未反映到有效期时续费应当被拦截');
        } catch (BusinessException $exception) {
            $this->assertStringContainsString('当前续费周期已完成', $exception->getMessage());
        }

        $this->assertSame(
            0,
            Invoice::query()
                ->where('service_id', (int) $service->id)
                ->where('type', OrderType::RENEW)
                ->where('status', InvoiceStatus::UNPAID)
                ->count()
        );
    }

    /**
     * @return array{0: User, 1: Service}
     */
    private function renewFixture(array $overrides): array
    {
        $paidAt = $overrides['paid_at'];
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
            'expires_at' => $overrides['expires_at'],
        ]);

        // 上一笔已支付且已登记履约的同周期月付账单（provision_data 指向它，判定为已履约）
        $fulfilledInvoice = Invoice::query()->create([
            'invoice_no' => Invoice::generateInvoiceNo(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'service_id' => $service->id,
            'type' => OrderType::RENEW,
            'amount' => 35.00,
            'status' => InvoiceStatus::PAID,
            'billing_cycle' => 'monthly',
            'paid_at' => $paidAt,
        ]);
        $service->forceFill([
            'provision_data' => ['last_renew_invoice_id' => (int) $fulfilledInvoice->id],
        ])->save();

        $this->fulfilledInvoiceId = (int) $fulfilledInvoice->id;

        return [$user, $service];
    }

    private int $fulfilledInvoiceId = 0;
}
