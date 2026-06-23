<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\VerificationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientVerificationInitValidationTest extends TestCase
{
    private function createUnverifiedUser(string $suffix): User
    {
        return User::query()->create([
            'email' => 'verification-init-'.$suffix.'@example.com',
            'password' => 'Temp@123456',
            'phone' => '13'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => 1,
            'is_verified' => 0,
            'verification_status' => 0,
        ]);
    }

    public function test_init_rejects_missing_realname(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = $this->createUnverifiedUser($suffix);
        $token = $user->createToken('client-verification-init-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/client/verification/init', [
                'idcard' => '320505199001010012',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('data.errors.realname.0', '请填写真实姓名。');
    }

    public function test_init_rejects_missing_idcard(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = $this->createUnverifiedUser($suffix);
        $token = $user->createToken('client-verification-init-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/client/verification/init', [
                'realname' => '张三',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonPath('data.errors.idcard.0', '请填写证件号码。');
    }

    public function test_init_rejects_invalid_cert_type(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = $this->createUnverifiedUser($suffix);
        $token = $user->createToken('client-verification-init-test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/client/verification/init', [
                'realname' => '张三',
                'idcard' => '320505199001010012',
                'cert_type' => 'INVALID_TYPE',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200)
            ->assertJsonStructure(['data' => ['errors' => ['cert_type']]]);
    }

    public function test_init_accepts_valid_cert_type(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = $this->createUnverifiedUser($suffix);
        $token = $user->createToken('client-verification-init-test')->plainTextToken;

        $fakeService = new class extends VerificationService
        {
            public function __construct() {}

            public function startVerificationSession($user, string $realname, string $idcard, string $certType = 'IDENTITY_CARD'): array
            {
                return ['certify_id' => 'FAKE-CERTIFY-ID', 'status' => 'pending'];
            }
        };

        $this->app->instance(VerificationService::class, $fakeService);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/client/verification/init', [
                'realname' => '张三',
                'idcard' => '320505199001010012',
                'cert_type' => 'HOME_VISIT_PERMIT_HK_MC',
            ]);

        $response->assertSuccessful();
        $this->assertArrayNotHasKey('errors', $response->json());
    }

    public function test_init_defaults_cert_type_to_identity_card_when_omitted(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = $this->createUnverifiedUser($suffix);
        $token = $user->createToken('client-verification-init-test')->plainTextToken;

        $receivedCertType = null;
        $fakeService = new class($receivedCertType) extends VerificationService
        {
            public function __construct(private ?string &$receivedCertType) {}

            public function startVerificationSession($user, string $realname, string $idcard, string $certType = 'IDENTITY_CARD'): array
            {
                $this->receivedCertType = $certType;

                return ['certify_id' => 'FAKE-CERTIFY-ID', 'status' => 'pending'];
            }
        };

        $this->app->instance(VerificationService::class, $fakeService);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/client/verification/init', [
                'realname' => '张三',
                'idcard' => '320505199001010012',
            ])
            ->assertSuccessful();

        $this->assertSame('IDENTITY_CARD', $receivedCertType);
    }

    public function test_fee_config_reads_admin_saved_verification_settings(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $user = $this->createUnverifiedUser($suffix);
        $token = $user->createToken('client-verification-fee-config-test')->plainTextToken;
        $keys = ['free_attempts', 'retry_fee'];
        $originalRows = DB::table('settings')
            ->where('group_key', 'verification')
            ->whereIn('item_key', $keys)
            ->get(['group_key', 'item_key', 'item_value'])
            ->map(fn (object $row): array => [
                'group_key' => (string) $row->group_key,
                'item_key' => (string) $row->item_key,
                'item_value' => $row->item_value,
            ])
            ->all();

        try {
            DB::table('settings')
                ->where('group_key', 'verification')
                ->whereIn('item_key', $keys)
                ->delete();

            Setting::setValue('verification', 'free_attempts', 5);
            Setting::setValue('verification', 'retry_fee', '8.50');

            $this->withHeader('Authorization', 'Bearer '.$token)
                ->getJson('/api/client/verification/fee-config')
                ->assertOk()
                ->assertJsonPath('data.free_attempts', 5)
                ->assertJsonPath('data.retry_fee', 8.5);
        } finally {
            DB::table('settings')
                ->where('group_key', 'verification')
                ->whereIn('item_key', $keys)
                ->delete();

            if ($originalRows !== []) {
                DB::table('settings')->insert($originalRows);
            }
        }
    }
}
