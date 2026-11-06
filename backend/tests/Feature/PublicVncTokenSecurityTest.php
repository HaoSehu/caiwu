<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Services\ClientServiceConsole\ServiceVncService;
use App\Support\Cache\VolatileStore;
use App\Support\CacheKey;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    // 无需再单独覆盖 redis_volatile：Tests\TestCase 已统一置为 array store。

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
        // 每次兑换必须签发独立 relay token：复用会让刷新/多页签/嵌入+新窗口的并发建连
        // 撞到同一份上游凭证，上游对同一 VNC token 的第二次并发握手直接拒绝（Relay 回 502）。
        $this->assertNotSame($first['token'], $second['token'], '每次兑换应签发独立 relay token');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{48}$/', (string) $second['token']);

        // 两个 relay token 各自指向同一份上游参数，互不影响。
        $relayFirst = VolatileStore::store()->get(CacheKey::vncToken((string) $first['token']));
        $relaySecond = VolatileStore::store()->get(CacheKey::vncToken((string) $second['token']));
        $this->assertIsArray($relayFirst);
        $this->assertIsArray($relaySecond);
        $this->assertSame($relayFirst['host'], $relaySecond['host']);
        $this->assertSame($relayFirst['port'], $relaySecond['port']);

        // relay 缓存参数同样不落明文密码，仅以 hash 维系统计与溯源
        $relayParams = VolatileStore::store()->get(CacheKey::vncToken((string) $first['token']));
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

    public function test_public_token_cannot_be_used_as_relay_connection_token(): void
    {
        $token = 'pub-relay-denied-'.uniqid();
        $this->putToken($token, [
            'token_scope' => 'public',
            'service_id' => 42,
            'host' => '10.0.0.5',
            'port' => 5901,
            'allowed_origin' => 'https://console.example.test',
        ]);

        $service = app(ServiceVncService::class);

        // relay 建连只认 60 秒 TTL 的 relay token；公开 token（10 分钟）不得直接建连。
        foreach (['previewVncToken', 'resolveVncToken'] as $method) {
            try {
                $service->{$method}($token);
                $this->fail($method.' 不得接受 public token');
            } catch (BusinessException $exception) {
                $this->assertSame(40400, $exception->getErrorCode());
            }
        }
    }

    /**
     * 并发刷新/多页签：同一公开 token 连续兑换得到的 relay token 必须彼此不同，
     * 否则多个浏览器连接会共用同一份上游凭证并被上游拒绝握手（表现为 502）。
     */
    public function test_each_exchange_issues_isolated_relay_token_for_concurrent_connections(): void
    {
        $token = 'pub-concurrent-'.uniqid();
        $this->putToken($token, [
            'token_scope' => 'public',
            'service_id' => 42,
            'username' => 'root',
            'host' => '10.0.0.5',
            'port' => 5901,
        ]);

        $service = app(ServiceVncService::class);

        $tokens = [];
        for ($i = 0; $i < 3; $i++) {
            $tokens[] = (string) $service->resolvePublicVncTokenPayload($token)['token'];
        }

        $this->assertCount(3, array_unique($tokens), '并发兑换必须各自拿到独立 relay token');

        foreach ($tokens as $relayToken) {
            $params = VolatileStore::store()->get(CacheKey::vncToken($relayToken));
            $this->assertIsArray($params, '每个 relay token 都应写入独立的建连参数');
            $this->assertSame('relay', (string) ($params['token_scope'] ?? ''));
            $this->assertSame(hash('sha256', $token), (string) ($params['public_token_hash'] ?? ''));
        }
    }

    public function test_public_token_exchange_is_capped_per_token(): void
    {
        $token = 'pub-cap-'.uniqid();
        $this->putToken($token, [
            'token_scope' => 'public',
            'service_id' => 42,
            'username' => 'root',
            'target' => 'host-42',
            'host' => '10.0.0.5',
            'port' => 5901,
        ]);

        $service = app(ServiceVncService::class);

        // 30 次以内允许（含首次），超出后必须拒绝，防止公开链接被脚本化无限兑换。
        for ($i = 0; $i < 30; $i++) {
            $service->resolvePublicVncTokenPayload($token);
        }

        try {
            $service->resolvePublicVncTokenPayload($token);
            $this->fail('超过兑换次数上限后必须拒绝');
        } catch (BusinessException $exception) {
            $this->assertSame(42900, $exception->getErrorCode());
            $this->assertSame(429, $exception->getCode());
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function putToken(string $token, array $params): void
    {
        VolatileStore::store()->put(
            CacheKey::vncToken($token),
            $params,
            now()->addMinutes(10),
        );
    }
}
