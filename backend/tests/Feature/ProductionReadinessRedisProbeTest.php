<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\System\ProductionReadinessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * 生产就绪探针的 Redis 分支：
 * 1. 未使用 redis 驱动时判为不适用并直接通过，绝不把「没装 Redis 的测试环境」报成故障；
 * 2. 缓存驱动指向 redis 但连接不可用时判失败，原因里带用途前缀与异常摘要，且不向外抛；
 * 3. 探针只发 PING，不写键、不 flush，避免探针自身污染键空间或统计。
 */
class ProductionReadinessRedisProbeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_redis_check_passes_without_reason_when_redis_is_not_used(): void
    {
        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'file',
        ]);

        $result = (new ProductionReadinessService)->check();

        $this->assertTrue($result['checks']['redis']);
        $this->assertArrayNotHasKey('redis', $result['reasons']);
    }

    public function test_redis_check_fails_with_purpose_prefixed_reason_when_connection_is_unavailable(): void
    {
        config([
            'database.redis.probe' => [
                'host' => '127.0.0.1',
                'password' => null,
                'port' => 1,
                'database' => 0,
                'timeout' => 0.2,
                'read_timeout' => 0.2,
            ],
            'cache.stores.probe' => ['driver' => 'redis', 'connection' => 'probe'],
            'cache.default' => 'probe',
            'queue.default' => 'sync',
            'session.driver' => 'file',
        ]);
        // 连接可能已被解析过，先丢弃，确保走真实建连失败路径而不是复用旧连接。
        Redis::purge('probe');

        $result = (new ProductionReadinessService)->check();

        $this->assertFalse($result['checks']['redis']);
        $this->assertArrayHasKey('redis', $result['reasons']);
        $this->assertStringStartsWith('缓存', $result['reasons']['redis']);
        $this->assertStringContainsString('连接不可用', $result['reasons']['redis']);
    }
}
