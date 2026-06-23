<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OperationLog;
use App\Models\User;
use App\Services\Auth\AuthService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientProfileRegressionTest extends TestCase
{
    public function test_client_profile_update_writes_users_table(): void
    {
        $user = User::query()->create([
            'email' => 'client-profile-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Old Nickname',
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

        Sanctum::actingAs($user);

        $this->putJson('/api/client/auth/profile', [
            'nickname' => 'Updated Nickname',
        ])
            ->assertOk()
            ->assertJsonPath('data.nickname', 'Updated Nickname')
            ->assertJsonPath('data.display_name', 'Updated Nickname');

        $this->assertDatabaseHas('users', [
            'id' => (int) $user->id,
            'nickname' => 'Updated Nickname',
        ]);
    }

    public function test_client_profile_update_writes_operation_log(): void
    {
        $user = User::query()->create([
            'email' => 'client-profile-log-'.bin2hex(random_bytes(4)).'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'nickname' => 'Old Nickname',
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

        Sanctum::actingAs($user);

        $traceId = 'client-profile-log-'.bin2hex(random_bytes(4));
        $userAgent = 'ClientProfileRegressionTest/1.0';

        $this->withHeaders([
            'X-Request-Id' => $traceId,
            'User-Agent' => $userAgent,
        ])->putJson('/api/client/auth/profile', [
            'nickname' => 'Logged Nickname',
        ])
            ->assertOk()
            ->assertJsonPath('data.nickname', 'Logged Nickname');

        $log = OperationLog::query()
            ->where('action', 'profile.nickname.update')
            ->where('module', 'auth')
            ->where('user_id', (int) $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('profile.nickname.update', $log->action);
        $this->assertSame('auth', $log->module);
        $this->assertSame((int) $user->id, (int) $log->user_id);

        $detail = $log->detail;

        $this->assertTrue(
            ($detail['trace_id'] ?? null) === $traceId || ($detail['user_agent'] ?? null) === $userAgent,
            'Expected operation log detail to include trace_id or user_agent.'
        );
    }

    public function test_client_register_writes_users_table(): void
    {
        $email = 'client-register-'.bin2hex(random_bytes(4)).'@example.com';

        $result = app(AuthService::class)->clientRegister([
            'account' => $email,
            'email' => $email,
            'password' => 'Temp@123456',
            'nickname' => 'Registered Nickname',
        ], '127.0.0.1');

        $this->assertIsArray($result);
        $this->assertSame('Registered Nickname', $result['user']['nickname'] ?? null);
        $this->assertIsString($result['token'] ?? null);
        $this->assertNotSame('', $result['token'] ?? '');

        $this->assertDatabaseHas('users', [
            'email' => $email,
            'nickname' => 'Registered Nickname',
        ]);
    }
}
