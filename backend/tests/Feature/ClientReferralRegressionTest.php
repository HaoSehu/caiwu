<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MemberLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientReferralRegressionTest extends TestCase
{
    public function test_referral_endpoints_work_without_user_accounts_and_account_transactions_tables(): void
    {
        MemberLevel::query()->firstOrCreate(
            ['code' => 'ref-test-default'],
            [
                'name' => 'v1',
                'sales_amount_min' => '0.00',
                'sales_amount_max' => '300.00',
                'reward_rate' => '5.00',
                'status' => 1,
                'sort_order' => 1,
            ]
        );

        $user = User::query()->create([
            'email' => 'referral-regression-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Referral Regression',
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

        DB::table('account_transactions')->insert([
            'user_id' => (int) $user->id,
            'account_type' => 'referral_frozen',
            'event_type' => 'reward_frozen',
            'change_amount' => '10.00',
            'balance_after' => '10.00',
            'source_type' => 'reward',
            'source_id' => 1,
            'origin_type' => 'referral_event',
            'origin_id' => 1,
            'remark' => 'referral regression',
            'operator' => 'system',
            'trace_id' => 'referral-regression',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/client/referral/overview')
            ->assertOk()
            ->assertJsonPath('data.referral_code', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('data.referral_frozen_amount', '0.00');

        $this->getJson('/api/client/referral/account-logs?per_page=30')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.event_type', 'reward_frozen')
            ->assertJsonPath('data.list.0.frozen_amount', '10.00');
    }
}
