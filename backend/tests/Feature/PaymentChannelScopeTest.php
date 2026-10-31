<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\InvoiceType;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use App\Support\PaymentChannelLabel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 支付渠道展示判据回归（迁移后的历史支付单）。
 *
 * 背景：旧库 payments 没有 plugin_key/plugin_name/payment_type/option_* 快照列，
 * 渠道事实只写在 gateway_key（大量为空）与回调标记里。展示侧若用
 * 「是否第三方真实资金流入」（isThirdPartyGateway）当判据，这批历史单据的
 * 支付方式会整条消失：账单 detail 的「支付方式」被 array_filter 掉、
 * 用户端支付单列表 total 不含该单、订单 payment_chain.payments 为空。
 *
 * 本测试锁定三件事：
 * 1. PHP 判据（hasKnownChannel）与 SQL 判据（channelScopeSql）选出的主键集合
 *    完全相同，含按渠道筛选——两侧不一致会出现「列表里看得到、筛选筛不出」；
 * 2. 回调能还原出的渠道要落到展示名（manual -> 手动入账、balance -> 余额支付），
 *    而 gateway_key 有值时以列为准；
 * 3. 回调也没有渠道线索时不臆造、不报错，只是不展示。
 *
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class PaymentChannelScopeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_php_and_sql_channel_scope_select_the_same_payments(): void
    {
        $this->makeLegacyPayment('SC-M', null, ['payment_gateway' => 'manual']);
        $this->makeLegacyPayment('SC-B', null, ['source' => 'balance']);
        $this->makeLegacyPayment('SC-AS', null, ['source' => 'alipay_precreate']);
        $this->makeLegacyPayment('SC-AK', null, ['notify_id' => 'N-1', 'trade_status' => 'TRADE_SUCCESS']);
        $this->makeLegacyPayment('SC-U', null, ['days' => 3, 'reason' => 'pending_recharge_expired', 'source' => 'automation']);
        $this->makeLegacyPayment('SC-G', PaymentGatewayCode::ALIPAY, []);

        $phpIds = Payment::query()
            ->with('callbacks')
            ->get()
            ->filter(static fn (Payment $payment): bool => PaymentChannelLabel::hasKnownChannel($payment))
            ->map(static fn (Payment $payment): int => (int) $payment->id)
            ->sort()
            ->values()
            ->all();

        $this->assertNotEmpty($phpIds);

        // 不指定渠道时，PHP 侧就是「有渠道的那批」；指定渠道时再按渠道键收窄。
        $this->assertSame($phpIds, $this->sqlScopedIds(''), 'PHP 与 SQL 的渠道判据不一致：(all)');

        foreach ([PaymentGatewayCode::ALIPAY, PaymentGatewayCode::YIPAY, PaymentGatewayCode::WECHAT, PaymentGatewayCode::MANUAL, PaymentGatewayCode::BALANCE] as $gateway) {
            $this->assertSame(
                $this->phpScopedIds($gateway),
                $this->sqlScopedIds($gateway),
                "PHP 与 SQL 的渠道判据不一致：{$gateway}"
            );
        }
    }

    public function test_legacy_payments_show_recovered_channel_names(): void
    {
        $manualInvoice = $this->makePaidInvoice('CM');
        $this->makeLegacyPayment('CM-P', null, ['payment_gateway' => 'manual'], $manualInvoice);

        $balanceInvoice = $this->makePaidInvoice('CB');
        $this->makeLegacyPayment('CB-P', null, ['source' => 'balance'], $balanceInvoice);

        $service = app(InvoiceService::class);

        $manual = $service->clientDetail($manualInvoice->fresh());
        $this->assertNotNull($manual['payment_summary'], '手动入账的历史支付单必须能给出支付方式');
        $this->assertSame(PaymentGatewayCode::MANUAL, $manual['payment_summary']['gateway']);
        $this->assertSame('手动入账', $manual['payment_summary']['gateway_label']);
        $this->assertSame('手动入账', $manual['payment_summary']['channel_name']);
        $this->assertSame('手动入账', $this->scenePaymentMethod($manual));

        $balance = $service->clientDetail($balanceInvoice->fresh());
        $this->assertNotNull($balance['payment_summary'], '余额支付的历史支付单必须能给出支付方式');
        $this->assertSame(PaymentGatewayCode::BALANCE, $balance['payment_summary']['gateway']);
        $this->assertSame('余额支付', $balance['payment_summary']['channel_name']);
        $this->assertSame('余额支付', $this->scenePaymentMethod($balance));
    }

    public function test_gateway_column_wins_over_callback_evidence(): void
    {
        $invoice = $this->makePaidInvoice('CC');
        $this->makeLegacyPayment('CC-P', PaymentGatewayCode::ALIPAY, ['source' => 'balance'], $invoice);

        $detail = app(InvoiceService::class)->clientDetail($invoice->fresh());

        $this->assertNotNull($detail['payment_summary']);
        $this->assertSame(PaymentGatewayCode::ALIPAY, $detail['payment_summary']['gateway'], 'gateway_key 有值时不得被回调证据改写');
        $this->assertSame('支付宝', $detail['payment_summary']['channel_name']);
    }

    public function test_payment_without_any_channel_evidence_is_hidden_not_fabricated(): void
    {
        $invoice = $this->makePaidInvoice('CU');
        $payment = $this->makeLegacyPayment('CU-P', null, ['days' => 3, 'reason' => 'pending_recharge_expired', 'source' => 'automation'], $invoice);

        $this->assertFalse(PaymentChannelLabel::hasKnownChannel($payment));
        $this->assertNotContains((int) $payment->id, $this->sqlScopedIds(''));

        $detail = app(InvoiceService::class)->clientDetail($invoice->fresh());

        // 不臆造渠道：没有支付方式，也不能因此报错或塞一个占位串。
        $this->assertNull($detail['payment_summary']);
        $this->assertSame('', $this->scenePaymentMethod($detail));
        $this->assertArrayHasKey('scene', $detail);
    }

    /**
     * @return list<int>
     */
    private function sqlScopedIds(string $gateway): array
    {
        [$sql, $bindings] = PaymentChannelLabel::channelScopeSql('payments', $gateway);

        return DB::table('payments')
            ->whereRaw($sql, $bindings)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function phpScopedIds(string $gateway): array
    {
        return Payment::query()
            ->with('callbacks')
            ->get()
            ->filter(static fn (Payment $payment): bool => PaymentChannelLabel::resolveGatewayKey($payment) === $gateway)
            ->map(static fn (Payment $payment): int => (int) $payment->id)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $callbackRaw
     */
    private function makeLegacyPayment(string $no, ?string $gatewayKey, array $callbackRaw, ?Invoice $invoice = null): Payment
    {
        $user = $this->makeUser();

        $payment = new Payment;
        $payment->payment_no = $no.'-'.uniqid();
        $payment->user_id = $user->id;
        $payment->invoice_id = $invoice?->id;
        $payment->gateway_key = $gatewayKey;
        $payment->amount = 10.00;
        $payment->status = $invoice === null ? PaymentStatus::CANCELLED : PaymentStatus::SUCCESS;
        $payment->paid_at = $invoice === null ? null : now();
        // 历史数据里的网关值可能是空、manual、balance，创建守卫只拦真实新建链路。
        $payment->allowNonThirdPartyGateway = true;
        $payment->callback_raw = $callbackRaw;
        $payment->save();

        return $payment;
    }

    private function makePaidInvoice(string $tag): Invoice
    {
        $user = $this->makeUser();

        return Invoice::query()->create([
            'invoice_no' => 'INV-'.$tag.'-'.uniqid(),
            'user_id' => $user->id,
            'type' => InvoiceType::NEW_PURCHASE,
            'amount' => '10.00',
            'paid_amount' => '10.00',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function scenePaymentMethod(array $detail): string
    {
        foreach ((array) ($detail['scene']['fields'] ?? []) as $field) {
            if (($field['label'] ?? '') === '支付方式') {
                return (string) ($field['value'] ?? '');
            }
        }

        return '';
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'payment-channel-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'payment-channel-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }
}
