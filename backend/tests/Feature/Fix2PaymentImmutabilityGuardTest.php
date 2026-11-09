<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\FinanceLedgerEventType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\FinanceLedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\Core\FinanceAccountService;
use App\Services\Finance\Core\FundingLotAllocator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

/**
 * M-68 / M-83：资金台账不可变、成功支付单不可物理删除，
 * 由模型钩子兜底，不再只依赖服务层纪律；funding_lot_id 回填等既有合法更新必须放行。
 */
class Fix2PaymentImmutabilityGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_updating_ledger_amount_throws(): void
    {
        $entry = $this->makeLedgerEntry();

        try {
            $entry->forceFill(['change_amount' => '99.00'])->save();
            $this->fail('修改台账金额必须被模型守卫拒绝');
        } catch (RuntimeException $exception) {
            $this->assertSame('资金台账行不可修改', $exception->getMessage());
        }

        $entry->refresh();
        $this->assertSame('10.00', (string) $entry->change_amount);
    }

    public function test_updating_ledger_funding_lot_id_is_allowed(): void
    {
        $entry = $this->makeLedgerEntry();
        $this->assertNull($entry->funding_lot_id);

        // 与生产回填路径一致：FundingLotAllocator 先建批次，再写台账 funding_lot_id。
        $account = app(FinanceAccountService::class)->accountFor((int) $entry->user_id);
        $lot = app(FundingLotAllocator::class)->createLot($account, $entry, 1000, 'normal', 'test_seed', null, null, null);

        $entry->refresh();
        $this->assertSame((int) $lot->id, (int) $entry->funding_lot_id);
    }

    public function test_deleting_success_payment_throws(): void
    {
        $payment = $this->makePayment(PaymentStatus::SUCCESS);

        try {
            $payment->delete();
            $this->fail('删除成功支付单必须被模型守卫拒绝');
        } catch (RuntimeException $exception) {
            $this->assertSame('已成功的支付单不可删除', $exception->getMessage());
        }

        $this->assertNotNull(Payment::query()->find((int) $payment->id));
    }

    public function test_deleting_non_success_payment_is_allowed(): void
    {
        $payment = $this->makePayment(PaymentStatus::PENDING);

        $payment->delete();

        $this->assertNull(Payment::query()->find((int) $payment->id));
    }

    private function makeLedgerEntry(): FinanceLedgerEntry
    {
        $user = User::query()->create([
            'email' => 'fix2-immutable-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'fix2-immutable-tester',
            'total_sales_amount' => 0,
        ]);
        $account = app(FinanceAccountService::class)->accountFor((int) $user->id);

        return FinanceLedgerEntry::query()->create([
            'entry_no' => 'FLFIX2'.strtoupper(uniqid()),
            'account_id' => (int) $account->id,
            'user_id' => (int) $user->id,
            'account_type' => 'cash',
            'event_type' => FinanceLedgerEventType::SYSTEM_ADJUSTMENT,
            'change_amount' => '10.00',
            'balance_after' => '10.00',
            'currency' => 'CNY',
        ]);
    }

    private function makePayment(int $status): Payment
    {
        $user = User::query()->create([
            'email' => 'fix2-payment-delete-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'fix2-payment-delete-tester',
            'total_sales_amount' => 0,
        ]);

        return Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 1.00,
            'status' => $status,
        ]);
    }
}
