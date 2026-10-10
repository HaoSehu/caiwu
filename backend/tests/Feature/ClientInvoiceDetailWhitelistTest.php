<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\InvoiceStatus;
use App\Constants\PaymentGatewayCode;
use App\Constants\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 用户端账单详情字段白名单回归（B5：V7-09 零覆盖缺口，BACKEND §9「用户端账单详情
 * 不返回管理端内部字段和第三方交易号」）：
 * 1. 原始回调（callback_raw）、内部审计字段（trace_id/commission/profit/settlement）、
 *    管理侧备注等一律不得出现；
 * 2. 白名单结构（basic/display/financial/payment_chain/payment_options/items）必须保留，
 *    避免过度裁剪破坏客户端渲染。
 * 口径说明：支付流水级 `trade_no`（第三方订单号）为客户端控制台既有展示字段
 * （payment-detail/payments 页面渲染「第三方订单号」），属产品现行为；
 * §9 字面「不返回第三方交易号」与现行为存在口径分歧，见 V7-09 记录，待产品裁决；
 * 本测试锁定「内部字段不穿透 + 白名单结构完整」两条底线。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class ClientInvoiceDetailWhitelistTest extends TestCase
{
    use DatabaseTransactions;

    public function test_client_invoice_detail_never_exposes_internal_fields(): void
    {
        $user = $this->makeUser();
        $invoice = Invoice::query()->create([
            'invoice_no' => 'INV-DETAIL-'.uniqid(),
            'user_id' => $user->id,
            'type' => 'new',
            'amount' => '100.00',
            'paid_amount' => '100.00',
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        Payment::query()->create([
            'payment_no' => 'PY-DETAIL-'.uniqid(),
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'gateway' => PaymentGatewayCode::ALIPAY,
            'amount' => 100.00,
            'status' => PaymentStatus::SUCCESS,
            'paid_at' => now(),
            'trade_no' => 'THIRDPARTY-TRADE-887766',
            'callback_raw' => [
                'refund' => ['refund_method' => 'balance'],
                'admin_note' => 'INTERNAL-CALLBACK-NOTE-XYZ',
            ],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v2/client/invoices/'.$invoice->id)->assertOk();

        $json = (string) $response->getContent();
        $this->assertNotSame('', $json);

        foreach ([
            'callback_raw',
            'INTERNAL-CALLBACK-NOTE-XYZ',
            'trace_id',
            'commission',
            'profit',
            'settlement',
            'admin_remark',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $json, "客户端账单详情不得出现内部字段 [{$needle}]");
        }

        $invoicePayload = $response->json('data.invoice');
        $this->assertIsArray($invoicePayload);
        $this->assertSame((int) $invoice->id, (int) ($invoicePayload['id'] ?? 0));

        foreach (['basic', 'display', 'financial', 'payment_chain', 'payment_options', 'items'] as $section) {
            $this->assertArrayHasKey($section, $invoicePayload, "白名单结构 [{$section}] 必须保留");
        }

        // 支付链保留自身流水号（用户对账需要），但不得带内部字段
        $payments = (array) ($invoicePayload['payment_chain']['payments'] ?? []);
        $this->assertNotEmpty($payments);
        $this->assertArrayNotHasKey('callback_raw', $payments[0]);
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'email' => 'invoice-detail-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => 'invoice-detail-tester',
            'total_sales_amount' => 0,
            'status' => 1,
        ]);
    }
}
