<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BalanceLog;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientFinanceBalanceLogRegressionTest extends TestCase
{
    public function test_balance_logs_endpoints_work_without_account_transactions_table(): void
    {
        $user = User::query()->create([
            'email' => 'finance-balance-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Finance Balance',
            'real_name' => '',
            'id_card' => '',
            'verification_status' => 0,
            'verification_message' => '',
            'verification_certify_id' => null,
            'member_level_id' => null,
            'total_sales_amount' => '0.00',
            'referrer_user_id' => null,
            'verified_at' => null,
        ]);

        BalanceLog::query()->create([
            'user_id' => (int) $user->id,
            'event_type' => 'recharge',
            'change_amount' => '88.00',
            'balance_after' => '88.00',
            'reference_id' => 1001,
            'remark' => 'balance regression',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/client/balance-logs?page=1&page_size=15')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.event_type', 'recharge')
            ->assertJsonPath('data.list.0.change_amount', '88.00');

        $this->getJson('/api/client/balance-logs/summary')
            ->assertOk()
            ->assertJsonPath('data.total_in', '88.00')
            ->assertJsonPath('data.total_out', '0.00');
    }
}
