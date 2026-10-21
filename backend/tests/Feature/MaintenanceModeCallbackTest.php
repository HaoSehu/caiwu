<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Contracts\Integrations\Payments\PaymentGatewayInterface;
use App\Models\FinanceCutoverInbox;
use App\Models\IntegrationPlugin;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginDomain;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 切换窗口维护模式（php artisan down）下的回调收件箱兜底：
 * 业务端点返回 503（不是被兜底渲染成 500），支付回调路由豁免维护模式、
 * 进入收件箱并 ack 成功——这是 Runbook「窗口内回调只存报文不丢」的实现保证。
 */
class MaintenanceModeCallbackTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    private ?PaymentGatewayInterface $stub = null;

    protected function tearDown(): void
    {
        config(['finance.cutover.inbox_enabled' => false]);
        parent::tearDown();
    }

    public function test_payment_callback_bypasses_maintenance_and_lands_in_inbox(): void
    {
        $this->registerStubGateway();
        $payment = $this->makePendingPayment();

        config(['finance.cutover.inbox_enabled' => true]);

        $this->app->maintenanceMode()->activate([]);

        try {
            $response = $this->post('/api/v2/client/payment/notify/alipay', [
                'out_trade_no' => $payment->payment_no,
                'trade_no' => 'TRADEMT'.mt_rand(100000, 999999),
                'trade_status' => 'TRADE_SUCCESS',
                'total_amount' => '100.00',
                'app_id' => 'stub-merchant',
            ]);

            // 网关必须拿到成功应答，否则会持续重试直到丢弃。
            $this->assertSame(200, $response->status());
            $this->assertSame('success', (string) $response->getContent());

            // 报文进收件箱，资金未处理（窗口内只暂存）。
            $this->assertSame(1, FinanceCutoverInbox::query()->count());
            $this->assertSame(PaymentStatus::PENDING, (int) $payment->refresh()->status);
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }
    }

    public function test_business_endpoint_returns_503_during_maintenance(): void
    {
        $this->app->maintenanceMode()->activate([]);

        try {
            $response = $this->getJson('/api/v2/client/invoices');

            // 关键：必须是 503，不能被兜底 renderer 吞成 5000/500。
            $this->assertSame(503, $response->status());
        } finally {
            $this->app->maintenanceMode()->deactivate();
        }
    }

    private function makePendingPayment(): Payment
    {
        $user = User::factory()->create();
        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'amount' => '100.00',
            'status' => InvoiceStatus::UNPAID,
        ]);

        $payment = Payment::query()->create([
            'payment_no' => 'PY'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'invoice_id' => (int) $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => '100.00',
            'status' => PaymentStatus::PENDING,
            'callback_raw' => ['source' => 'alipay_precreate'],
        ]);

        return $payment;
    }

    private function registerStubGateway(): void
    {
        if (Schema::hasTable('integration_plugins')) {
            IntegrationPlugin::query()
                ->where('domain', PluginDomain::PAYMENT)
                ->update(['status' => IntegrationPlugin::STATUS_DISABLED]);
        }

        $this->stub = new class implements PaymentGatewayInterface
        {
            public function key(): string
            {
                return PaymentGatewayCode::ALIPAY;
            }

            public function name(): string
            {
                return '维护模式测试替身网关';
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function matchesMerchantId(?string $merchantId): bool
            {
                return (string) $merchantId === 'stub-merchant';
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
        };

        app(PaymentGatewayRegistry::class)->register($this->stub);
    }
}
