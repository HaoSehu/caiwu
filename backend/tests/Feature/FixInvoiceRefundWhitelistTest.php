<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\InvoiceRefundService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 审查修复回归（P0-1 / M-61）：
 * - 「退款到余额」类型闸必须是正向白名单（new/normal/renew/upgrade），
 *   充值/补录/扣款/推荐奖励一律拒绝——黑名单漏掉的 referral_credit 曾可退款凭空造钱；
 * - 可退额实收基数改为 paid_amount > 0 ? paid_amount : amount，
 *   少付的 PAID 账单（paid=50/amount=100）不得算出超过实收的可退额。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class FixInvoiceRefundWhitelistTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    public function test_refund_type_whitelist_allows_purchase_types(): void
    {
        $service = app(InvoiceRefundService::class);

        $this->assertTrue($service->isRefundToBalanceTypeAllowed(InvoiceType::NEW_PURCHASE));
        $this->assertTrue($service->isRefundToBalanceTypeAllowed('normal'), '历史值 normal 必须归一为 new 放行');
        $this->assertTrue($service->isRefundToBalanceTypeAllowed(InvoiceType::RENEW));
        $this->assertTrue($service->isRefundToBalanceTypeAllowed(InvoiceType::UPGRADE));
    }

    public function test_refund_type_whitelist_rejects_non_purchase_types(): void
    {
        $service = app(InvoiceRefundService::class);

        $this->assertFalse($service->isRefundToBalanceTypeAllowed(InvoiceType::RECHARGE));
        $this->assertFalse($service->isRefundToBalanceTypeAllowed(InvoiceType::MANUAL));
        $this->assertFalse($service->isRefundToBalanceTypeAllowed(InvoiceType::DEDUCTION));
        $this->assertFalse($service->isRefundToBalanceTypeAllowed(InvoiceType::REFERRAL_CREDIT));
        $this->assertFalse($service->isRefundToBalanceTypeAllowed(InvoiceType::REFUND));
    }

    public function test_referral_credit_invoice_refund_is_refused(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 0.00);
        $invoice = $this->makeInvoice($user, 100.00, ['type' => InvoiceType::REFERRAL_CREDIT]);
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        try {
            app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
            $this->fail('推荐奖励账单不得退回余额');
        } catch (BusinessException $error) {
            $this->assertSame('当前账单类型不支持退回余额（仅新购/续费/附加配置可退）', $error->getMessage());
        }

        $this->assertEqualsWithDelta(0.00, $this->financeBalance($user), 0.001, '被拒绝的退款不得产生任何余额变动');
    }

    public function test_deduction_invoice_refund_is_refused(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 0.00);
        $invoice = $this->makeInvoice($user, 100.00, ['type' => InvoiceType::DEDUCTION]);
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 100.00, 'paid_at' => now()])->save();

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('当前账单类型不支持退回余额（仅新购/续费/附加配置可退）');
        app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);
    }

    public function test_legacy_normal_type_invoice_still_refunds(): void
    {
        $user = $this->makeUser();
        $this->seedFinanceBalance($user, 0.00);
        $invoice = $this->makeInvoice($user, 40.00, ['type' => 'normal']);
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 40.00, 'paid_at' => now()])->save();

        $result = app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);

        $this->assertFalse((bool) ($result['already_refunded'] ?? true));
        $this->assertEqualsWithDelta(40.00, $this->financeBalance($user), 0.001);
    }

    public function test_all_three_purchase_types_refund_end_to_end(): void
    {
        foreach ([InvoiceType::NEW_PURCHASE, InvoiceType::RENEW, InvoiceType::UPGRADE] as $type) {
            $user = $this->makeUser();
            $this->seedFinanceBalance($user, 0.00);
            $invoice = $this->makeInvoice($user, 30.00, ['type' => $type]);
            $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 30.00, 'paid_at' => now()])->save();

            $result = app(InvoiceRefundService::class)->refundInvoiceToBalance($user, $invoice);

            $this->assertFalse((bool) ($result['already_refunded'] ?? true), "类型 {$type} 必须可退");
            $this->assertEqualsWithDelta(30.00, $this->financeBalance($user), 0.001, "类型 {$type} 退款必须到账");
        }
    }

    public function test_refundable_amount_uses_paid_amount_when_partially_paid(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 50.00, 'paid_at' => now()])->save();

        // 实收 50：可退额必须是 50，不能按应收 100 虚高。
        $this->assertEqualsWithDelta(50.00, app(InvoiceRefundService::class)->markRefundableAmountOf($invoice), 0.001);
    }

    public function test_refundable_amount_falls_back_to_invoice_amount_when_nothing_paid(): void
    {
        $user = $this->makeUser();
        $invoice = $this->makeInvoice($user, 100.00);
        $invoice->forceFill(['status' => InvoiceStatus::PAID, 'paid_amount' => 0.00, 'paid_at' => now()])->save();

        $this->assertEqualsWithDelta(100.00, app(InvoiceRefundService::class)->markRefundableAmountOf($invoice), 0.001);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'refund-whitelist-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'refund-whitelist-tester',
            'total_sales_amount' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeInvoice(User $user, float $amount, array $overrides = []): Invoice
    {
        return Invoice::query()->create(array_merge([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ], $overrides));
    }
}
