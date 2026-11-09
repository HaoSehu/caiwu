<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\V2\AdminManualEntryV2Service;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * M-81：补录交易号查重不再只按用户维度——同一交易号出现在其他用户名下同样拒绝，
 * 但只拦「同号不同用户」，不新增全局唯一约束。
 */
class Fix2ManualTradeNoGlobalDuplicateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_trade_no_used_by_another_user_is_rejected(): void
    {
        $owner = $this->makeUser('owner');
        $other = $this->makeUser('other');
        $tradeNo = 'GLOBALDUP-'.uniqid();

        $this->service()->createManualInvoice($owner, [
            'amount' => '50.00',
            'trade_no' => $tradeNo,
            'remark' => '第一笔',
        ], $this->context());

        try {
            $this->service()->createManualInvoice($other, [
                'amount' => '50.00',
                'trade_no' => $tradeNo,
                'remark' => '跨用户复用同一交易号',
            ], $this->context());
            $this->fail('Expected BusinessException for trade_no used by another user');
        } catch (BusinessException $exception) {
            $this->assertSame('该交易号已被其他用户使用，请核实', $exception->getMessage());
        }

        $this->assertSame(0, Invoice::query()->where('user_id', (int) $other->id)->count());
    }

    public function test_trade_no_used_by_another_users_pending_payment_is_rejected(): void
    {
        $owner = $this->makeUser('pending-owner');
        $other = $this->makeUser('pending-other');
        $tradeNo = 'PENDINGDUP-'.uniqid();

        Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $owner->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 1.00,
            'status' => PaymentStatus::PENDING,
            'trade_no' => $tradeNo,
        ]);

        try {
            $this->service()->createManualInvoice($other, [
                'amount' => '20.00',
                'trade_no' => $tradeNo,
                'remark' => '他人待支付单占用的交易号',
            ], $this->context());
            $this->fail('Expected BusinessException for trade_no owned by another user');
        } catch (BusinessException $exception) {
            $this->assertSame('该交易号已被其他用户使用，请核实', $exception->getMessage());
        }
    }

    public function test_same_user_trade_no_keeps_user_scoped_message(): void
    {
        $user = $this->makeUser('same-user');
        $tradeNo = 'SELFDUP-'.uniqid();

        $this->service()->createManualInvoice($user, [
            'amount' => '30.00',
            'trade_no' => $tradeNo,
            'remark' => '第一笔',
        ], $this->context());

        try {
            $this->service()->createManualInvoice($user, [
                'amount' => '30.00',
                'trade_no' => $tradeNo,
                'remark' => '同用户重复交易号',
            ], $this->context());
            $this->fail('Expected BusinessException for duplicated trade_no within one user');
        } catch (BusinessException $exception) {
            $this->assertSame('该交易号已存在于该用户的其他支付记录中，请核实后重新填写', $exception->getMessage());
        }
    }

    private function service(): AdminManualEntryV2Service
    {
        return app(AdminManualEntryV2Service::class);
    }

    private function makeUser(string $label): User
    {
        return User::query()->create([
            'email' => 'fix2-manual-'.$label.'-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'fix2-manual-'.$label,
            'total_sales_amount' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(): array
    {
        return [
            'operator_id' => 1,
            'operator_name' => 'admin-test',
            'trace_id' => 'fix2-'.uniqid(),
            'ip_address' => '127.0.0.1',
        ];
    }
}
