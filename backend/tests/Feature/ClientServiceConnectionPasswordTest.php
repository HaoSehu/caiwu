<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Constants\ServiceStatus;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 用户控制台"登录凭据"卡片依赖连接接口返回明文密码（展示/复制/重置后回显）。
 * 2026-08-23 提交 9ccb5b3a 误将 ServiceController::connection 的 includePassword
 * 关闭为 false，导致用户端密码不可见，此为回归护栏。
 */
class ClientServiceConnectionPasswordTest extends TestCase
{
    use DatabaseTransactions;

    public function test_connection_returns_plaintext_password(): void
    {
        $password = bin2hex(random_bytes(12));
        [$user, $service] = $this->connectionFixture($password);

        Sanctum::actingAs($user);

        $this->getJson("/api/v2/client/services/{$service->id}/connection")
            ->assertOk()
            ->assertJsonPath('data.connection.password', $password)
            ->assertJsonPath('data.connection.has_password', true)
            ->assertJsonPath('data.connection.username', 'root');
    }

    public function test_connection_reports_no_password_when_unset(): void
    {
        [$user, $service] = $this->connectionFixture('');

        Sanctum::actingAs($user);

        $this->getJson("/api/v2/client/services/{$service->id}/connection")
            ->assertOk()
            ->assertJsonPath('data.connection.password', '')
            ->assertJsonPath('data.connection.has_password', false);
    }

    public function test_connection_requires_authentication(): void
    {
        [, $service] = $this->connectionFixture(bin2hex(random_bytes(12)));

        $this->getJson("/api/v2/client/services/{$service->id}/connection")
            ->assertStatus(401);
    }

    public function test_other_user_cannot_read_connection(): void
    {
        [, $service] = $this->connectionFixture(bin2hex(random_bytes(12)));
        $intruder = User::factory()->create();

        Sanctum::actingAs($intruder);

        $this->getJson("/api/v2/client/services/{$service->id}/connection")
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);
    }

    /**
     * @return array{0: User, 1: Service}
     */
    private function connectionFixture(string $password): array
    {
        $user = User::factory()->create();
        $product = Product::query()->create([
            'product_type' => 'cloud_host',
            'status' => 1,
            'pricing' => ['monthly' => '35.00'],
        ]);
        $service = Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'billing_cycle' => 'monthly',
            'amount' => 35.00,
            'status' => ServiceStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
            'provision_data' => [
                'connection_secret' => Crypt::encryptString(json_encode([
                    'hostname' => 'sv1.example.test',
                    'username' => 'root',
                    'password' => $password,
                    'port' => 22,
                    'internal_ip' => '10.0.0.5',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            ],
        ]);

        return [$user, $service];
    }
}
