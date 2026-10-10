<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Services\ClientServiceConsole\ServiceVncService;
use App\Support\CacheKey;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * 公开 VNC token 安全回归（B5：V7-03 零覆盖缺口，BACKEND §9「公开 VNC token 不返回密码、
 * 重复消费失效、过期失效、日志脱敏」）：
 * 1. 公开兑换载荷不返回 VNC 密码、不回传原始公开 token，relay 参数不落明文密码；
 * 2. 兑换幂等（§7 现行口径：同一 token 在 TTL 内可重复兑换；§9「一次性消费」为旧口径，
 *    以 §7 与代码注释为准，本测试锁定幂等行为）；
 * 3. 过期/未知 token 与服务作用域不匹配一律拒绝；
 * 4. 每次兑换生成独立 relay token（48 位 hex）。
 * token 存储于 volatile 缓存：测试中以 array 驱动替换，避免依赖 Redis。
 * 使用 DatabaseTransactions，测试结束回滚。
 */
class PublicVncTokenSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.stores.redis_volatile' => ['driver' => 'array']]);
    }

    public function test_public_payload_hides_password_and_raw_token_and_is_idempotent(): void
    {
        $token = 'pub-'.uniqid();
        $this->putToken($token, [
            'token_scope' => 'public',
            'service_id' => 42,
            'username' => 'root',
            'target' => 'host-42',
            'host' => '10.0.0.5',
            'port' => 5901,
            'password' => 'SUPER-SECRET-VNC-PW',
        ]);

        $service = app(ServiceVncService::class);

        $first = $service->resolvePublicVncTokenPayload($token);
        $second = $service->resolvePublicVncTokenPayload($token);

        $encoded = json_encode([$first, $second], JSON_UNESCAPED_UNICODE);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('SUPER-SECRET-VNC-PW', $encoded, '公开载荷不得包含 VNC 密码');
        $this->assertStringNotContainsString($token, $encoded, '不得回传原始公开 token');
        $this->assertArrayNotHasKey('password', $first);

        $this->assertSame(42, $first['service_id']);
        $this->assertSame('root', $first['username']);
        $this->assertSame('host-42', $first['target']);
        $this->assertGreaterThan(0, (int) $first['expires_in']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{48}$/', (string) $first['token']);
        $this->assertNotSame($first['token'], $second['token'], '每次兑换应生成独立 relay token');

        // relay 缓存参数同样不落明文密码，仅以 hash 维系统计与溯源
        $relayParams = Cache::store('redis_volatile')->get(CacheKey::vncToken((string) $first['token']));
        $this->assertIsArray($relayParams);
        $this->assertArrayNotHasKey('password', $relayParams, 'relay 参数不得落明文 VNC 密码');
        $this->assertSame(hash('sha256', $token), (string) ($relayParams['public_token_hash'] ?? ''));
        $this->assertSame('relay', (string) ($relayParams['token_scope'] ?? ''));
    }

    public function test_unknown_or_expired_token_is_rejected(): void
    {
        try {
            app(ServiceVncService::class)->resolvePublicVncTokenPayload('unknown-'.uniqid());
            $this->fail('未知 token 必须被拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame(40400, $exception->getErrorCode());
            $this->assertStringContainsString('已过期或无效', $exception->getMessage());
        }
    }

    public function test_relay_scope_token_cannot_be_redeemed_as_public(): void
    {
        $relayToken = bin2hex(random_bytes(24));
        $this->putToken($relayToken, [
            'token_scope' => 'relay',
            'service_id' => 42,
            'host' => '10.0.0.5',
            'port' => 5901,
        ]);

        try {
            app(ServiceVncService::class)->resolvePublicVncTokenPayload($relayToken);
            $this->fail('relay 作用域 token 不得经公开兑换入口消费');
        } catch (BusinessException $exception) {
            $this->assertSame(40400, $exception->getErrorCode());
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function putToken(string $token, array $params): void
    {
        Cache::store('redis_volatile')->put(
            CacheKey::vncToken($token),
            $params,
            now()->addMinutes(10),
        );
    }
}
