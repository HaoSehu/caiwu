<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Services\Finance\CheckoutSecurityService;
use Tests\TestCase;

/**
 * quote_token 与会员折扣层的一致性：报价时签发的 token 必须携带会员折扣金额，
 * 下单时会员折扣变化将被防篡改校验拒绝。
 */
class QuoteTokenMemberDiscountTest extends TestCase
{
    private CheckoutSecurityService $security;

    protected function setUp(): void
    {
        parent::setUp();
        $this->security = app(CheckoutSecurityService::class);
    }

    public function test_quote_token_roundtrip_with_member_discount(): void
    {
        $issued = $this->security->issueQuoteToken(1, 'monthly', ['os' => 1], [
            'quantity' => 1,
            'subtotal_amount' => 100.00,
            'base_amount' => 100.00,
            'config_amount' => 0,
            'setup_fee' => 0,
            'discount_amount' => 5.00,
            'member_discount_amount' => 10.00,
            'total_amount' => 85.00,
        ]);

        $payload = $this->security->assertQuoteToken(
            $issued['quote_token'],
            1,
            'monthly',
            1,
            ['os' => 1],
            '100.00',
            '85.00',
            null,
            '10.00'
        );

        $this->assertSame('10.00', $payload['member_discount_amount']);
    }

    public function test_quote_token_rejects_changed_member_discount(): void
    {
        $issued = $this->security->issueQuoteToken(1, 'monthly', ['os' => 1], [
            'quantity' => 1,
            'subtotal_amount' => 100.00,
            'base_amount' => 100.00,
            'config_amount' => 0,
            'setup_fee' => 0,
            'discount_amount' => 0,
            'member_discount_amount' => 10.00,
            'total_amount' => 90.00,
        ]);

        $this->expectException(BusinessException::class);

        $this->security->assertQuoteToken(
            $issued['quote_token'],
            1,
            'monthly',
            1,
            ['os' => 1],
            '100.00',
            '90.00',
            null,
            '0.00' // 会员折扣与签发时不一致
        );
    }

    public function test_legacy_token_without_member_discount_matches_zero(): void
    {
        // 模拟旧版本签发的 token（payload 中无 member_discount_amount 键）
        $issued = $this->security->issueQuoteToken(1, 'monthly', ['os' => 1], [
            'quantity' => 1,
            'subtotal_amount' => 100.00,
            'config_amount' => 0,
            'setup_fee' => 0,
            'discount_amount' => 0,
            'total_amount' => 100.00,
        ]);
        unset($issued['quote_expires_at']);

        $payload = $this->security->assertQuoteToken(
            $issued['quote_token'],
            1,
            'monthly',
            1,
            ['os' => 1],
            '100.00',
            '100.00'
        );

        $this->assertSame('0.00', $payload['member_discount_amount'] ?? '0.00');
    }
}
