<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Models\IntegrationPlugin;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\CheckoutService;
use App\Services\Finance\MixPaymentService;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\User\AccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 组合支付支付单复用防护回归（修复前：余额退回后再次发起混付会复用
 * 已标记 balance_restored 的旧支付单，再次取消时被回补幂等闸拦截，
 * 本次预扣余额被永久冻结）。修复后：balance_restored 的旧单不复用，
 * 第二次混付新建支付单，其预扣余额可正常退回。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class MixPaymentReuseGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_restored_mix_payment_is_not_reused_and_second_deposit_is_refundable(): void
    {
        $user = $this->makeUser();
        $accounts = app(AccountService::class);
        $accounts->setCashBalance($user, 100.00);
        $this->registerStubGateway();
        $service = app(MixPaymentService::class);

        // 第一次混付：预扣 30，网关单 70 保持 PENDING。
        $invoice = $this->makeUnpaidInvoice($user, 100.00);
        $service->payByBalanceAndGateway($invoice, $user, 30.00, PaymentGatewayCode::ALIPAY);
        $first = Payment::query()
            ->where('invoice_id', (int) $invoice->id)
            ->whereGatewayKey(PaymentGatewayCode::ALIPAY)
            ->firstOrFail();

        // 支付窗口过期路径退回预扣余额：余额回 100，支付单保持 PENDING 且标记 balance_restored。
        $this->assertTrue($service->restoreReservedMixBalance($first, [
            'closed_reason' => 'payment_window_expired_captured',
            'mark_payment_failed' => false,
        ]));
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);
        $first->refresh();
        $this->assertSame(PaymentStatus::PENDING, (int) $first->status);
        $this->assertNotEmpty((array) ($first->callback_raw ?? [])['balance_restored'] ?? null);
        $invoice->forceFill(['status' => InvoiceStatus::UNPAID, 'paid_amount' => 0])->save();

        // 第二次混付：修复后不复用已退回的旧单，而是新建支付单。
        $service->payByBalanceAndGateway($invoice->fresh(), $user, 30.00, PaymentGatewayCode::ALIPAY);
        $this->assertEqualsWithDelta(70.00, $accounts->cashBalance($user), 0.001);

        $pendingPayments = Payment::query()
            ->where('invoice_id', (int) $invoice->id)
            ->whereGatewayKey(PaymentGatewayCode::ALIPAY)
            ->where('status', PaymentStatus::PENDING)
            ->orderBy('id')
            ->get();
        $this->assertSame(2, $pendingPayments->count(), '已退回的旧单不得被复用，应新建第二张支付单');
        $this->assertSame((int) $first->id, (int) $pendingPayments->first()->id);

        // 用户再次取消：第二张单的预扣 30 必须能正常退回（修复前复用旧单会被幂等闸拦截，余额冻结在 70）。
        app(CheckoutService::class)->cancel($invoice->fresh());
        $this->assertEqualsWithDelta(100.00, $accounts->cashBalance($user), 0.001);
    }

    private function registerStubGateway(): void
    {
        if (Schema::hasTable('integration_plugins')) {
            IntegrationPlugin::query()
                ->where('domain', PluginDomain::PAYMENT)
                ->update(['status' => IntegrationPlugin::STATUS_DISABLED]);
        }

        app(PaymentGatewayRegistry::class)->register(new class implements PaymentGatewayInterface
        {
            public function key(): string
            {
                return PaymentGatewayCode::ALIPAY;
            }

            public function name(): string
            {
                return '混付复用防护测试替身网关';
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function matchesMerchantId(?string $merchantId): bool
            {
                return true;
            }

            public function supportsAction(string $action): bool
            {
                return false;
            }

            public function precreate(PaymentPrecreateRequest $request): PaymentPrecreateResult
            {
                return new PaymentPrecreateResult(
                    qrCode: 'https://stub.gateway.test/qr/'.rawurlencode($request->outTradeNo),
                    outTradeNo: $request->outTradeNo,
                    raw: ['stub' => true],
                );
            }

            public function query(string $outTradeNo): PaymentQueryResult
            {
                return new PaymentQueryResult(
                    tradeStatus: 'WAIT_BUYER_PAY',
                    tradeNo: '',
                    outTradeNo: $outTradeNo,
                    totalAmount: '0.00',
                    raw: ['stub' => true],
                );
            }

            public function verifyNotify(array $payload): bool
            {
                return true;
            }

            public function buildNotifyResponse(bool $success): Response
            {
                return response($success ? 'success' : 'fail', 200)
                    ->header('Content-Type', 'text/plain');
            }
        });
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'mix-reuse-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'mix-reuse-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeUnpaidInvoice(User $user, float $amount): Invoice
    {
        $order = Order::query()->create([
            'order_no' => 'ODREUSE'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => $amount,
            'status' => 0,
        ]);

        return Invoice::query()->create([
            'invoice_no' => 'IVREUSE'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => InvoiceType::NEW_PURCHASE,
            'amount' => $amount,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
        ]);
    }
}
