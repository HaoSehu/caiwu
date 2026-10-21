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
use App\Services\Finance\GatewayInvoicePaymentService;
use App\Services\Integrations\Payments\Data\PaymentPrecreateRequest;
use App\Services\Integrations\Payments\Data\PaymentPrecreateResult;
use App\Services\Integrations\Payments\Data\PaymentQueryResult;
use App\Services\Integrations\Payments\PaymentGatewayRegistry;
use App\Services\Integrations\Plugins\PluginDomain;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SeedsFinanceBalance;
use Tests\TestCase;

/**
 * 切换窗口收件箱：窗口开启时支付回调只存报文并 ack 成功；
 * 窗口解除后 finance:replay-cutover-inbox 重放入账。
 */
class FinanceCutoverInboxTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsFinanceBalance;

    private ?PaymentGatewayInterface $stub = null;

    protected function tearDown(): void
    {
        config(['finance.cutover.inbox_enabled' => false]);
        parent::tearDown();
    }

    public function test_notify_is_captured_without_processing_when_inbox_enabled(): void
    {
        $user = $this->makeUser();
        $this->registerStubGateway();
        $invoice = $this->makeInvoice($user, 100.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();

        config(['finance.cutover.inbox_enabled' => true]);
        $response = $this->post('/api/v2/client/payment/notify/alipay', [
            'out_trade_no' => $payment->payment_no,
            'trade_no' => 'TRADECAP'.mt_rand(100000, 999999),
            'trade_status' => 'TRADE_SUCCESS',
            'total_amount' => '100.00',
            'app_id' => 'stub-merchant',
        ]);

        // 渠道侧拿到成功应答（不触发重试风暴），但资金未被处理。
        $this->assertSame(200, $response->status());
        $this->assertSame('success', (string) $response->getContent());

        $payment->refresh();
        $this->assertSame(PaymentStatus::PENDING, (int) $payment->status);

        $inbox = FinanceCutoverInbox::query()->where('channel', PaymentGatewayCode::ALIPAY)->first();
        $this->assertNotNull($inbox);
        $this->assertSame(FinanceCutoverInbox::STATUS_PENDING, (string) $inbox->status);
        $this->assertStringContainsString((string) $payment->payment_no, (string) $inbox->notification_id);
        $this->assertStringContainsString('TRADE_SUCCESS', (string) $inbox->payload);
    }

    public function test_replay_command_settles_captured_callback(): void
    {
        $user = $this->makeUser();
        $this->registerStubGateway();
        $invoice = $this->makeInvoice($user, 100.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();

        $tradeNo = 'TRADEREP'.mt_rand(100000, 999999);
        FinanceCutoverInbox::query()->create([
            'channel' => PaymentGatewayCode::ALIPAY,
            'notification_id' => $payment->payment_no.':'.$tradeNo.':replay',
            'request_uri' => '/api/v2/client/payment/notify/alipay',
            'payload' => json_encode([
                'out_trade_no' => $payment->payment_no,
                'trade_no' => $tradeNo,
                'trade_status' => 'TRADE_SUCCESS',
                'total_amount' => '100.00',
                'app_id' => 'stub-merchant',
            ], JSON_UNESCAPED_UNICODE),
            'status' => FinanceCutoverInbox::STATUS_PENDING,
            'received_at' => now(),
        ]);

        // 窗口未解除时拒绝重放，防止重放与新回调互相穿插。
        config(['finance.cutover.inbox_enabled' => true]);
        $this->artisan('finance:replay-cutover-inbox')->assertExitCode(1);
        config(['finance.cutover.inbox_enabled' => false]);

        $this->artisan('finance:replay-cutover-inbox')->assertExitCode(0);

        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->status);
        $this->assertSame($tradeNo, (string) $payment->trade_no);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PAID, (int) $invoice->status);

        $inbox = FinanceCutoverInbox::query()->firstOrFail();
        $this->assertSame(FinanceCutoverInbox::STATUS_REPLAYED, (string) $inbox->status);
        $this->assertNotNull($inbox->replayed_at);

        // 幂等：重复重放不再产生第二次入账。
        $this->artisan('finance:replay-cutover-inbox')->assertExitCode(0);
        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->refresh()->status);
    }

    public function test_replay_keeps_failed_row_pending_with_error_message(): void
    {
        $this->registerStubGateway();
        // 金额与任何支付单都不匹配：入账闸拒绝，报文保留待人工核对。
        FinanceCutoverInbox::query()->create([
            'channel' => PaymentGatewayCode::ALIPAY,
            'notification_id' => 'missing:payment:'.mt_rand(100000, 999999),
            'request_uri' => '/api/v2/client/payment/notify/alipay',
            'payload' => json_encode([
                'out_trade_no' => 'PYNOTEXISTS'.mt_rand(100000, 999999),
                'trade_no' => 'TRADEGHOST'.mt_rand(100000, 999999),
                'trade_status' => 'TRADE_SUCCESS',
                'total_amount' => '66.00',
                'app_id' => 'stub-merchant',
            ], JSON_UNESCAPED_UNICODE),
            'status' => FinanceCutoverInbox::STATUS_PENDING,
            'received_at' => now(),
        ]);

        $this->artisan('finance:replay-cutover-inbox')->assertExitCode(1);

        $inbox = FinanceCutoverInbox::query()->firstOrFail();
        $this->assertSame(FinanceCutoverInbox::STATUS_PENDING, (string) $inbox->status);
        $this->assertNotNull($inbox->error_msg);
    }

    public function test_replay_exits_non_zero_when_some_rows_fail(): void
    {
        $user = $this->makeUser();
        $this->registerStubGateway();
        $invoice = $this->makeInvoice($user, 100.00);
        $payload = app(GatewayInvoicePaymentService::class)->payByGateway($invoice, $user, PaymentGatewayCode::ALIPAY);
        $payment = Payment::query()->where('payment_no', $payload['payment_no'])->firstOrFail();

        $tradeNo = 'TRADEOK'.mt_rand(100000, 999999);
        FinanceCutoverInbox::query()->create([
            'channel' => PaymentGatewayCode::ALIPAY,
            'notification_id' => $payment->payment_no.':'.$tradeNo.':mixed-ok',
            'request_uri' => '/api/v2/client/payment/notify/alipay',
            'payload' => json_encode([
                'out_trade_no' => $payment->payment_no,
                'trade_no' => $tradeNo,
                'trade_status' => 'TRADE_SUCCESS',
                'total_amount' => '100.00',
                'app_id' => 'stub-merchant',
            ], JSON_UNESCAPED_UNICODE),
            'status' => FinanceCutoverInbox::STATUS_PENDING,
            'received_at' => now(),
        ]);

        // 同批次再塞一条金额对不上的脏报文：成功与失败混合时不能报全绿。
        FinanceCutoverInbox::query()->create([
            'channel' => PaymentGatewayCode::ALIPAY,
            'notification_id' => 'mixed-bad:'.mt_rand(100000, 999999),
            'request_uri' => '/api/v2/client/payment/notify/alipay',
            'payload' => json_encode([
                'out_trade_no' => 'PYMIXEDMISS'.mt_rand(100000, 999999),
                'trade_no' => 'TRADEMIXBAD'.mt_rand(100000, 999999),
                'trade_status' => 'TRADE_SUCCESS',
                'total_amount' => '66.00',
                'app_id' => 'stub-merchant',
            ], JSON_UNESCAPED_UNICODE),
            'status' => FinanceCutoverInbox::STATUS_PENDING,
            'received_at' => now(),
        ]);

        // 用 Artisan::call 直接取退出码与输出：expectsOutputToContain 只捕获标准输出，
        // 而失败计数同时出现在 error() 写出的告警行里，需要一并断言。
        $exitCode = Artisan::call('finance:replay-cutover-inbox');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('成功 1', $output);
        $this->assertStringContainsString('失败 1', $output);
        $this->assertStringContainsString('退出码置为失败以便告警', $output);

        $this->assertSame(PaymentStatus::SUCCESS, (int) $payment->refresh()->status);
        $this->assertSame(FinanceCutoverInbox::STATUS_REPLAYED, (string) FinanceCutoverInbox::query()
            ->where('notification_id', $payment->payment_no.':'.$tradeNo.':mixed-ok')
            ->firstOrFail()->status);
        $this->assertSame(FinanceCutoverInbox::STATUS_PENDING, (string) FinanceCutoverInbox::query()
            ->where('notification_id', 'like', 'mixed-bad:%')
            ->firstOrFail()->status);
    }

    private function makeUser(): User
    {
        $user = User::factory()->create();

        return $user;
    }

    private function makeInvoice(User $user, float $amount): Invoice
    {
        $invoice = Invoice::query()->create([
            'invoice_no' => 'IV'.date('YmdHis').mt_rand(100000, 999999),
            'user_id' => (int) $user->id,
            'type' => 'new',
            'amount' => number_format($amount, 2, '.', ''),
            'status' => InvoiceStatus::UNPAID,
        ]);

        return $invoice;
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
                return '收件箱测试替身网关';
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
